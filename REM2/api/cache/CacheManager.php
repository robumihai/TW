<?php
/**
 * Cache Manager for External API Responses
 * 
 * Handles caching of external API responses to improve performance
 * and reduce API calls to third-party services
 */

class CacheManager {
    private $cachePath;
    private $config;
    private $maxSize; // in MB
    private $cleanupInterval;
    
    public function __construct($config = null) {
        if ($config === null) {
            // Try different paths for config file
            $configPaths = [
                '../config/external_apis.php',
                'config/external_apis.php',
                './config/external_apis.php'
            ];
            
            foreach ($configPaths as $path) {
                if (file_exists($path)) {
                    $config = require_once $path;
                    break;
                }
            }
            
            if ($config === null) {
                throw new Exception('Configuration file not found');
            }
        }
        
        $this->config = $config;
        $this->cachePath = isset($this->config['cache']['storage_path']) ? $this->config['cache']['storage_path'] : 'api/cache/data';
        $this->maxSize = isset($this->config['cache']['max_size']) ? $this->config['cache']['max_size'] : 100;
        $this->cleanupInterval = isset($this->config['cache']['cleanup_interval']) ? $this->config['cache']['cleanup_interval'] : 3600;
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0777, true);
        }
        
        // Randomly trigger cleanup
        $cleanupProb = isset($this->config['cache']['cleanup_probability']) ? $this->config['cache']['cleanup_probability'] : 10;
        if (rand(1, 100) <= $cleanupProb) {
            $this->cleanup();
        }
    }
    
    /**
     * Generate cache key from parameters
     */
    private function generateKey($service, $endpoint, $params = []) {
        $keyData = [
            'service' => $service,
            'endpoint' => $endpoint,
            'params' => $params
        ];
        return md5(json_encode($keyData));
    }
    
    /**
     * Get cache file path
     */
    private function getCacheFilePath($key) {
        return $this->cachePath . '/' . $key . '.cache';
    }
    
    /**
     * Store data in cache
     */
    public function set($service, $endpoint, $params, $data, $ttl = null) {
        try {
            $key = $this->generateKey($service, $endpoint, $params);
            $filePath = $this->getCacheFilePath($key);
            
            $ttl = $ttl ?: (isset($this->config['cache']['default_duration']) ? $this->config['cache']['default_duration'] : 3600);
            $expiresAt = time() + $ttl;
            
            $cacheData = [
                'key' => $key,
                'service' => $service,
                'endpoint' => $endpoint,
                'params' => $params,
                'data' => $data,
                'created_at' => time(),
                'expires_at' => $expiresAt,
                'size' => strlen(json_encode($data))
            ];
            
            $result = file_put_contents($filePath, json_encode($cacheData));
            
            if ($result === false) {
                $this->logError("Failed to write cache file: $filePath");
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logError("Cache set error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retrieve data from cache
     */
    public function get($service, $endpoint, $params = []) {
        try {
            $key = $this->generateKey($service, $endpoint, $params);
            $filePath = $this->getCacheFilePath($key);
            
            if (!file_exists($filePath)) {
                return null;
            }
            
            $content = file_get_contents($filePath);
            if ($content === false) {
                return null;
            }
            
            $cacheData = json_decode($content, true);
            if (!$cacheData) {
                // Invalid cache file, remove it
                @unlink($filePath);
                return null;
            }
            
            // Check if cache has expired
            if (time() > $cacheData['expires_at']) {
                @unlink($filePath);
                return null;
            }
            
            return $cacheData['data'];
            
        } catch (Exception $e) {
            $this->logError("Cache get error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if cache exists and is valid
     */
    public function has($service, $endpoint, $params = []) {
        return $this->get($service, $endpoint, $params) !== null;
    }
    
    /**
     * Delete specific cache entry
     */
    public function delete($service, $endpoint, $params = []) {
        try {
            $key = $this->generateKey($service, $endpoint, $params);
            $filePath = $this->getCacheFilePath($key);
            
            if (file_exists($filePath)) {
                return @unlink($filePath);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logError("Cache delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        try {
            $files = glob($this->cachePath . '/*.cache');
            $deleted = 0;
            
            foreach ($files as $file) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
            
            return $deleted;
            
        } catch (Exception $e) {
            $this->logError("Cache clear error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Cleanup expired cache entries
     */
    public function cleanup() {
        try {
            $files = glob($this->cachePath . '/*.cache');
            $deleted = 0;
            $currentTime = time();
            
            foreach ($files as $file) {
                $content = @file_get_contents($file);
                if ($content === false) {
                    continue;
                }
                
                $cacheData = json_decode($content, true);
                if (!$cacheData) {
                    // Invalid cache file, remove it
                    @unlink($file);
                    $deleted++;
                    continue;
                }
                
                // Check if expired
                if ($currentTime > $cacheData['expires_at']) {
                    @unlink($file);
                    $deleted++;
                }
            }
            
            return $deleted;
            
        } catch (Exception $e) {
            $this->logError("Cache cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        try {
            $files = glob($this->cachePath . '/*.cache');
            $totalSize = 0;
            $totalFiles = 0;
            $expiredFiles = 0;
            $services = [];
            $currentTime = time();
            
            foreach ($files as $file) {
                $totalFiles++;
                $size = filesize($file);
                $totalSize += $size;
                
                $content = @file_get_contents($file);
                if ($content !== false) {
                    $cacheData = json_decode($content, true);
                    if ($cacheData) {
                        $service = $cacheData['service'];
                        if (!isset($services[$service])) {
                            $services[$service] = 0;
                        }
                        $services[$service]++;
                        
                        if ($currentTime > $cacheData['expires_at']) {
                            $expiredFiles++;
                        }
                    }
                }
            }
            
            return [
                'total_files' => $totalFiles,
                'total_size_bytes' => $totalSize,
                'total_size_mb' => round($totalSize / (1024 * 1024), 2),
                'expired_files' => $expiredFiles,
                'services' => $services,
                'cache_path' => $this->cachePath
            ];
            
        } catch (Exception $e) {
            $this->logError("Cache stats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if cache size exceeds limit and cleanup if needed
     */
    public function checkSizeLimit() {
        $stats = $this->getStats();
        if ($stats['total_size_mb'] > $this->maxSize) {
            // First, cleanup expired files
            $deleted = $this->cleanup();
            
            // If still over limit, remove oldest files
            $stats = $this->getStats();
            if ($stats['total_size_mb'] > $this->maxSize) {
                $this->removeOldestFiles();
            }
            
            return true;
        }
        return false;
    }
    
    /**
     * Remove oldest cache files
     */
    private function removeOldestFiles() {
        try {
            $files = glob($this->cachePath . '/*.cache');
            $fileData = [];
            
            foreach ($files as $file) {
                $content = @file_get_contents($file);
                if ($content !== false) {
                    $cacheData = json_decode($content, true);
                    if ($cacheData) {
                        $fileData[] = [
                            'file' => $file,
                            'created_at' => $cacheData['created_at']
                        ];
                    }
                }
            }
            
            // Sort by creation time (oldest first)
            usort($fileData, function($a, $b) {
                return $a['created_at'] - $b['created_at'];
            });
            
            // Remove oldest 25% of files
            $toRemove = ceil(count($fileData) * 0.25);
            for ($i = 0; $i < $toRemove; $i++) {
                @unlink($fileData[$i]['file']);
            }
            
        } catch (Exception $e) {
            $this->logError("Remove oldest files error: " . $e->getMessage());
        }
    }
    
    /**
     * Log error messages
     */
    private function logError($message) {
        $logFile = $this->cachePath . '/cache_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
} 