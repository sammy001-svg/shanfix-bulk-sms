<?php
/**
 * White-Label Branding Engine - Shanfix Technology
 * Detects reseller context based on domain or user session
 */

class Branding {
    private static $settings = null;
    private static $is_white_labeled = false;

    public static function init() {
        if (self::$settings !== null) return;

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $reseller = null;

        // 1. Try to find reseller by domain
        $reseller = DB::queryOne("SELECT * FROM reseller_settings WHERE custom_domain = ?", [$host]);
        
        // 2. If not found by domain, check logged-in user's context
        if (!$reseller && isset($_SESSION['user']['id'])) {
            $user = $_SESSION['user'];
            
            // ADMINS ALWAYS SEE MAIN BRAND
            if ($user['role'] === 'admin') {
                $reseller = null; 
            } else {
                $resellerId = null;
                if ($user['role'] === 'reseller') {
                    $resellerId = $user['id'];
                } elseif ($user['role'] === 'client' && !empty($user['parent_id'])) {
                    $resellerId = $user['parent_id'];
                }

                if ($resellerId) {
                    $reseller = DB::queryOne("SELECT * FROM reseller_settings WHERE reseller_id = ?", [$resellerId]);
                }
            }
        }

        if ($reseller) {
            self::$settings = $reseller;
            self::$is_white_labeled = true;
        } else {
            // 3. Fallback to system settings
            self::$settings = [
                'system_name'   => get_setting('site_name', 'Shanfix Technology'),
                'system_logo'   => get_setting('site_logo', '/assets/images/logo.png'),
                'primary_color' => '#00c896',
                'sidebar_color' => '#0e1726',
                'support_email' => get_setting('support_email'),
                'support_phone' => get_setting('support_phone')
            ];
        }
    }

    public static function get($key, $default = null) {
        self::init();
        return self::$settings[$key] ?? $default;
    }

    public static function isWhiteLabeled() {
        self::init();
        return self::$is_white_labeled;
    }

    /**
     * Injects dynamic CSS variables into the page
     */
    public static function renderStyles() {
        $primary = self::get('primary_color', '#00c896');
        $sidebar = self::get('sidebar_color', '#0e1726');
        echo "
        <style>
            :root {
                --primary: {$primary};
                --primary-rgb: " . self::hexToRgb($primary) . ";
                --primary-hover: " . self::adjustBrightness($primary, -10) . ";
                --sidebar-bg: {$sidebar};
                --sidebar-hover: " . self::adjustBrightness($sidebar, 10) . ";
                --sidebar-active: rgba(" . self::hexToRgb($primary) . ", 0.15);
            }
        </style>";
    }

    private static function hexToRgb($hex) {
        $hex = str_replace("#", "", $hex);
        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }
        return "$r, $g, $b";
    }

    private static function adjustBrightness($hex, $steps) {
        $steps = max(-255, min(255, $steps));
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
        }
        $color_parts = str_split($hex, 2);
        $return = '#';
        foreach ($color_parts as $color) {
            $color = hexdec($color);
            $color = max(0, min(255, $color + $steps));
            $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT);
        }
        return $return;
    }
}
