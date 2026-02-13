#!/usr/bin/env ruby

require 'rubygems'
require 'dnsruby'
require 'ipaddress'
require 'optparse'
require 'logger'
require 'thread'

# Initialize logger
LOGGER = Logger.new($stderr)
LOGGER.level = Logger::INFO

# Default values
DEFAULT_DOMAINS = [
  # Freemail providers
  "openpgp.org", "t-online.de", "telekom.de", "gmail.com", "googlemail.com", "google.com",
  "gmx.net", "gmx.com", "gmx.de", "web.de", "aol.com", "microsoft.com", "outlook.com",
  "live.com", "live.de", "msn.com",
  # Social
  "facebook.com", "instagram.com", "threads.com", "meta.com", "twitter.com", "x.com",
  "pinterest.com", "reddit.com", "linkedin.com", "xing.com", "xing.de",
  # Commerce
  "amazon.com", "amazon.de", "paypal.com", "paypal.de", "klarna.com", "klarna.de",
  "booking.com", "ebay.com", "ebay.de",
  # Bulk sender / misc
  "github.com", "openwall.com", "freebsd.org"
]
DEFAULT_OUTPUT = "/usr/local/etc/postfix/postscreen_whitelist.cidr"

# Option parsing
options = {
  domains: nil,
  output: DEFAULT_OUTPUT,
  force: false,
  loglevel: "info",
  threads: 10
}

OptionParser.new do |opts|
  opts.banner = "Usage: postscreen_whitelist.rb [options]"
  opts.on("-dDOMAINS", "--domains=DOMAINS", "Comma-separated list of domains or path to file") do |d|
    options[:domains] = d
  end
  opts.on("-oFILE", "--output=FILE", "Output file (default: #{DEFAULT_OUTPUT})") do |o|
    options[:output] = o
  end
  opts.on("-f", "--force", "Force overwrite even if result count differs >10%") do
    options[:force] = true
  end
  opts.on("-lLEVEL", "--loglevel=LEVEL", "Logger level (debug, info, warn, error)") do |l|
    options[:loglevel] = l
  end
  opts.on("-tN", "--threads=N", Integer, "Number of parallel DNS threads (default: 10)") do |t|
    options[:threads] = t
  end
  opts.on("-h", "--help", "Print help") do
    puts opts
    exit
  end
end.parse!

LOGGER.level = Logger.const_get(options[:loglevel].upcase) rescue Logger::INFO

# Load domains from file or string
def load_domains(domains_arg)
  return DEFAULT_DOMAINS unless domains_arg
  if File.exist?(domains_arg)
    File.read(domains_arg).lines.map(&:strip).reject { |l| l.empty? || l.start_with?("#") }
  else
    domains_arg.split(",").map(&:strip)
  end
end

domains = load_domains(options[:domains])

# DNS cache (thread-safe)
class DnsCache
  def initialize
    @cache = {}
    @mutex = Mutex.new
  end
  def fetch(key)
    @mutex.synchronize { @cache[key] }
  end
  def store(key, value)
    @mutex.synchronize { @cache[key] = value }
  end
end

dns_cache = DnsCache.new

# DNS helpers (with caching)
def dns_query(resolver, name, type, cache)
  key = "#{name}:#{type}"
  if (cached = cache.fetch(key))
    return cached
  end
  begin
    records = resolver.getresources(name, type)
    cache.store(key, records)
    records
  rescue Dnsruby::ResolvError, Timeout::Error => e
    LOGGER.debug("DNS error for #{name} #{type}: #{e}")
    cache.store(key, [])
    []
  end
end

def a(names, resolver, cache)
  names.flat_map do |name|
    dns_query(resolver, name, "AAAA", cache) + dns_query(resolver, name, "A", cache)
  end.map { |r| r.address.to_s.downcase }
end

def mx(name, resolver, cache)
  dns_query(resolver, name, "MX", cache).flat_map { |r| a([r.exchange], resolver, cache) }
end

def get_spf_results(domain, resolver, cache)
  result = []
  txt_records = dns_query(resolver, domain, "TXT", cache) + dns_query(resolver, domain, "SPF", cache)
  spf_lines = txt_records.map { |r| r.strings.join }.uniq.select { |s| s =~ /^v=spf1/ }
  spf_lines.each do |line|
    line.split(/\s+/).each do |entry|
      next if entry == "v=spf1"
      case entry
      when /^redirect=(.+)/ then return get_spf_results($1, resolver, cache)
      when /^\??include:(.+)/ then result += get_spf_results($1, resolver, cache)
      when /^\??ip4:(.+)/ then result << $1
      when /^\??ip6:(.+)/ then result << $1
      when /^\??mx$/ then result += mx(domain, resolver, cache)
      when /^\??mx:(.+)/ then result += mx($1, resolver, cache)
      when /^\??a$/ then result += a([domain], resolver, cache)
      when /^\??a:(.+)/ then result += a([$1], resolver, cache)
      when /\.all/ then next
      else
        LOGGER.debug("Unrecognized SPF entry: domain=#{domain} entry=#{entry}")
      end
    end
  end
  # Normalize netmasks
  result.map! do |r|
    if m = r.match(/^(\d+\.\d+\.\d+\.\d+)\/(\d+)$/)
      i = IPAddress(r)
      "#{i.network.address}/#{i.network.prefix}"
    else
      r
    end
  end
  result.sort.uniq
end

# Parallel SPF fetching
def fetch_all_spf(domains, resolver, cache, thread_count)
  results = []
  queue = Queue.new
  domains.each { |d| queue << d }
  threads = Array.new(thread_count) do
    Thread.new do
      while !queue.empty?
        domain = queue.pop(true) rescue nil
        next unless domain
        begin
          spf = get_spf_results(domain, resolver, cache)
          LOGGER.info("Fetched SPF for #{domain}: #{spf.count} entries")
          results.concat(spf)
        rescue => e
          LOGGER.error("Failed to fetch SPF for #{domain}: #{e}")
        end
      end
    end
  end
  threads.each(&:join)
  results.uniq.sort
end

# File diffing and writing
def count_lines(file)
  File.exist?(file) ? File.read(file).lines.count : 0
end

old_lines = count_lines(options[:output])

resolver = Dnsruby::DNS.open
spf_results = fetch_all_spf(domains, resolver, dns_cache, options[:threads])

if old_lines > 0 && spf_results.count > 0
  ratio = old_lines.to_f / spf_results.count
  if (ratio < 0.9 || ratio > 1.1)
    LOGGER.warn("More than 10% difference in line count: old: #{old_lines}, new: #{spf_results.count}")
    unless options[:force]
      LOGGER.warn("Run with --force to overwrite anyway.")
      exit 1
    end
  end
end

# Backup old file
if File.exist?(options[:output])
  backup_file = "#{options[:output]}.bak"
  File.write(backup_file, File.read(options[:output]))
  LOGGER.info("Backup of old file saved at #{backup_file}")
end

File.write(options[:output], spf_results.join(" permit\n") + " permit\n")
LOGGER.info("Whitelist written to #{options[:output]} (#{spf_results.count} entries)")
