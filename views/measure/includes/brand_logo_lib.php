<?php
/**
 * Mapping marques → fichiers logo (public/images/).
 * PHP 7.3
 */

if (!function_exists('medacapBrandLogoMap')) {
    function medacapBrandLogoMap()
    {
        return array(
            'RENAULT TRUCK' => 'renaultTrucks.png',
            'HINO' => 'hino_logo.png',
            'TOYOTA BT' => 'bt.png',
            'SINOTRUK' => 'sinotruk.png',
            'JCB' => 'jcb.png',
            'MERCEDES TRUCK' => 'mercedestruck.png',
            'TOYOTA FORKLIFT' => 'forklift.png',
            'FUSO' => 'fuso.png',
            'LOVOL' => 'lovol.png',
            'KING LONG' => 'kl2.png',
            'MERCEDES' => 'mercedestruck.png',
            'TOYOTA' => 'toyota-logo.png',
            'SUZUKI' => 'suzuki-logo.png',
            'PEUGEOT' => 'peugeot-logo.png',
            'MITSUBISHI' => 'mitsubishi-logo.png',
            'FORD' => 'ford-logo.png',
            'CITROEN' => 'peugeot-logo.png',
            'RENAULT' => 'renaultTrucks.png',
            'VOLKSWAGEN' => 'ford-logo.png',
            'BYD' => 'toyota-logo.png',
        );
    }

    function medacapBrandLogoFile($brand)
    {
        $brand = trim((string) $brand);
        if ($brand === '') {
            return '';
        }

        $map = medacapBrandLogoMap();
        $upper = strtoupper($brand);
        if (isset($map[$upper])) {
            return $map[$upper];
        }
        if (isset($map[$brand])) {
            return $map[$brand];
        }
        foreach ($map as $name => $file) {
            if (strcasecmp($name, $brand) === 0) {
                return $file;
            }
        }

        return '';
    }

    /**
     * Chemin web relatif depuis views/measure/*.php
     */
    function medacapBrandLogoWebPath($brand)
    {
        $file = medacapBrandLogoFile($brand);
        if ($file === '') {
            return '';
        }

        return '../../public/images/' . $file;
    }
}
