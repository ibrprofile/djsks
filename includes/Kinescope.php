<?php
/**
 * Класс для работы с Kinescope API
 * Документация: https://documenter.getpostman.com/view/10589901/TVCcXpNM
 */

defined('APP_ACCESS') or die('Direct access not allowed');

class Kinescope {
    private $api_key;
    private $base_url = 'https://api.kinescope.io/v1';
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: getenv('KINESCOPE_API_KEY');
    }
    
    /**
     * Выполняет запрос к API Kinescope
     */
    private function request($method, $endpoint, $data = null) {
        if (empty($this->api_key)) {
            throw new Exception('Kinescope API key not configured');
        }
        
        $url = $this->base_url . $endpoint;
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 400) {
            error_log("Kinescope API error: HTTP $http_code - $response");
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Получает информацию о видео
     */
    public function getVideo($video_id) {
        return $this->request('GET', "/videos/{$video_id}");
    }
    
    /**
     * Получает ссылку на плеер для видео
     */
    public function getPlayerUrl($video_id) {
        return "https://kinescope.io/{$video_id}";
    }
    
    /**
     * Получает iframe код для встраивания плеера
     */
    public function getEmbedCode($video_id, $width = 640, $height = 360, $autoplay = false) {
        $url = $this->getPlayerUrl($video_id);
        $autoplay_param = $autoplay ? '&autoplay=1' : '';
        
        return sprintf(
            '<iframe src="%s?embed=1%s" width="%d" height="%d" frameborder="0" allowfullscreen allow="autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer" style="position:absolute;top:0;left:0;width:100%%;height:100%%"></iframe>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            $autoplay_param,
            (int)$width,
            (int)$height
        );
    }
    
    /**
     * Получает HLS ссылку для видео
     */
    public function getHlsUrl($video_id) {
        $video = $this->getVideo($video_id);
        if ($video && isset($video['data']['assets']['hls'])) {
            return $video['data']['assets']['hls'];
        }
        return null;
    }
    
    /**
     * Проверяет, доступно ли видео в прямом эфире
     */
    public function isLive($video_id) {
        $video = $this->getVideo($video_id);
        return $video && isset($video['data']['status']) && $video['data']['status'] === 'live';
    }
    
    /**
     * Валидирует формат Video ID
     */
    public static function validateVideoId($video_id) {
        // Kinescope video ID обычно формата: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $video_id);
    }
}
