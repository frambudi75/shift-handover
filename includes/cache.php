<?php
// includes/cache.php - Redis Caching Layer with Safe In-Memory / Graceful Fallback
// Mencegah lag dan query berulang pada database untuk performa tinggi (high concurrency).

class OmitCache {
    private static ?Redis $redisInstance = null;
    private static bool $checkedConnection = false;
    private static bool $isAvailable = false;
    private static array $memoryCache = [];

    /**
     * Inisialisasi koneksi Redis secara aman dengan timeout cepat (0.5s).
     * Jika Redis mati atau belum terpasang, sistem otomatis fallback tanpa error.
     */
    public static function getClient(): ?Redis {
        if (self::$checkedConnection) {
            return self::$redisInstance;
        }

        self::$checkedConnection = true;

        // Cek ekstensi PHP Redis
        if (!class_exists('Redis')) {
            self::$isAvailable = false;
            return null;
        }

        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('REDIS_PORT') ?: 6379);
        $pass = getenv('REDIS_PASS') ?: null;
        $prefix = getenv('REDIS_PREFIX') ?: 'omit_cache:';

        try {
            $redis = new Redis();
            // Timeout 0.5 detik agar aplikasi tidak pernah lag jika Redis offline
            $connected = @$redis->connect($host, $port, 0.5);
            if ($connected) {
                if (!empty($pass)) {
                    $redis->auth($pass);
                }
                $redis->setOption(Redis::OPT_PREFIX, $prefix);
                $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
                self::$redisInstance = $redis;
                self::$isAvailable = true;
                return self::$redisInstance;
            }
        } catch (Throwable $e) {
            // Silently fallback jika gagal
            self::$redisInstance = null;
            self::$isAvailable = false;
        }

        return null;
    }

    /**
     * Periksa apakah koneksi Redis sedang aktif
     */
    public static function isRedisActive(): bool {
        self::getClient();
        return self::$isAvailable;
    }

    /**
     * Ambil data dari cache
     */
    public static function get(string $key, $default = null) {
        $client = self::getClient();
        if ($client !== null) {
            try {
                $val = $client->get($key);
                if ($val !== false) {
                    return $val;
                }
            } catch (Throwable $e) {
                // Fallback ke memory cache jika terjadi timeout saat read
            }
        }

        return self::$memoryCache[$key] ?? $default;
    }

    /**
     * Simpan data ke cache dengan masa berlaku (TTL)
     */
    public static function set(string $key, $value, int $ttl = 300): bool {
        self::$memoryCache[$key] = $value;
        $client = self::getClient();
        if ($client !== null) {
            try {
                return (bool)$client->setex($key, $ttl, $value);
            } catch (Throwable $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * Hapus kunci tertentu dari cache
     */
    public static function delete(string $key): bool {
        unset(self::$memoryCache[$key]);
        $client = self::getClient();
        if ($client !== null) {
            try {
                return (bool)$client->del($key);
            } catch (Throwable $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * Kosongkan seluruh cache aplikasi
     */
    public static function flush(): bool {
        self::$memoryCache = [];
        $client = self::getClient();
        if ($client !== null) {
            try {
                $keys = $client->keys('*');
                if (!empty($keys)) {
                    $client->del($keys);
                }
                return true;
            } catch (Throwable $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * Cache Remember: Ambil dari cache jika ada, atau jalankan callback dan simpan hasilnya
     */
    public static function remember(string $key, int $ttl, callable $callback) {
        $val = self::get($key);
        if ($val !== null) {
            return $val;
        }

        $fresh = $callback();
        self::set($key, $fresh, $ttl);
        return $fresh;
    }

    /**
     * Dapatkan informasi status Redis untuk halaman Pengaturan & Backup
     */
    public static function getStats(): array {
        $active = self::isRedisActive();
        $stats = [
            'active'       => $active,
            'client'       => $active ? 'Redis Server' : (class_exists('Redis') ? 'Standalone In-Memory Fallback (Redis Server Offline)' : 'In-Memory Request Cache (Redis Extension Belum Terpasang)'),
            'host'         => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port'         => (int)(getenv('REDIS_PORT') ?: 6379),
            'memory_human' => '0 B',
            'keys_count'   => count(self::$memoryCache),
            'uptime'       => '-'
        ];

        if ($active && self::$redisInstance) {
            try {
                $info = self::$redisInstance->info();
                $stats['memory_human'] = isset($info['used_memory_human']) ? $info['used_memory_human'] : ($info['used_memory'] ?? '0 B');
                $stats['uptime'] = isset($info['uptime_in_seconds']) ? format_uptime((int)$info['uptime_in_seconds']) : '-';
                $keys = self::$redisInstance->keys('*');
                $stats['keys_count'] = is_array($keys) ? count($keys) : 0;
            } catch (Throwable $e) {
                // Abaikan jika info gagal
            }
        }

        return $stats;
    }
}

// Wrapper Functions Global untuk kemudahan pemanggilan
function cache_get(string $key, $default = null) {
    return OmitCache::get($key, $default);
}

function cache_set(string $key, $value, int $ttl = 300): bool {
    return OmitCache::set($key, $value, $ttl);
}

function cache_delete(string $key): bool {
    return OmitCache::delete($key);
}

function cache_flush(): bool {
    return OmitCache::flush();
}

function cache_remember(string $key, int $ttl, callable $callback) {
    return OmitCache::remember($key, $ttl, $callback);
}

function cache_is_redis_active(): bool {
    return OmitCache::isRedisActive();
}

function cache_get_stats(): array {
    return OmitCache::getStats();
}

function format_uptime(int $seconds): string {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    if ($days > 0) return "{$days}h {$hours}j";
    if ($hours > 0) return "{$hours}j {$minutes}m";
    return "{$seconds} detik";
}
