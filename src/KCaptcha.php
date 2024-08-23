<?php

namespace AJUR\Template;

/**
 * KCAPTCHA PROJECT
 *
 * Automatic test to tell computers and humans apart
 */
class KCaptcha implements KCaptchaInterface
{
    private array $timing = [];

    /**
     * @var string
     */
    private string $alphabet = "0123456789abcdefghijklmnopqrstuvwxyz"; # do not change without changing font files!

    /**
     * symbols used to draw CAPTCHA
     *
     * "0123456789"; #digits
     * "23456789abcdegkmnpqsuvxyz"; #alphabet without similar symbols (o=0, 1=l, i=j, t=f)
     *
     * @var string
     */
    private string $allowed_symbols = "23456789abcdegikpqsvxyz"; #alphabet without similar symbols (o=0, 1=l, i=j, t=f)

    /**
     * folder with fonts
     *
     * @var string
     */
    private string $fontsdir = 'fonts';

    // CAPTCHA image size (you do not need to change it, this parameters is optimal)
    /**
     * @var int
     */
    private int $width = 160;

    /**
     * @var int
     */
    private int $height = 80;

    /**
     * @var int
     */
    private int $length = 5;

    /**
     * symbol's vertical fluctuation amplitude
     *
     * @var int
     */
    private int $fluctuation_amplitude = 8;

    /**
     * White noise density (0 - no)
     *
     * @var float|int
     */
    private $white_noise_density = 1 / 6;

    /**
     * Black noise density (0 - no)
     *
     * @var float|int
     */
    private $black_noise_density = 1 / 30;

    /**
     * increase safety by prevention of spaces between symbols
     *
     * @var bool
     */
    private bool $no_spaces = true;

    #
    /**
     * show credits
     * set false to remove credits line. Credits string adds 12 pixels to image height
     *
     * @var bool
     */
    private bool $show_credits = false;

    /**
     * Credit string
     * if empty, HTTP_HOST will be shown
     *
     * @var string
     */
    private string $credits = 'www.captcha.ru';

    /**
     * @var array
     */
    private $foreground_color;

    /**
     * @var array
     */
    private $background_color;

    /**
     * JPEG quality of CAPTCHA image (bigger is better quality, but larger file size)
     *
     * @var int
     */
    private $jpeg_quality = 90;

    /**
     * @var int (-1 default, 0..9)
     */
    private $png_quality = -1;

    /**
     * generates key string and image
     *
     * @var string
     */
    private string $keystring;

    /**
     * @var false|\GdImage|resource
     */
    private $image_resource;

    /**
     * @var string
     */
    private $imageType = 'jpeg';

    /**
     * FONTS
     */
    /**
     * Доступные шрифтовые файлы
     * @var array
     */
    private array $available_font_files = [];

    /**
     * @var array Загруженные шрифты (width, height, metrics)
     */
    private array $fonts = [];

    /**
     * Количество загруженных шрифтов
     *
     * @var int
     */
    private int $fonts_count = 0;

    /**
     * @var bool
     */
    private bool $use_distortion = true;


    /**
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->timing['current'] = \microtime(true);
        
        # CAPTCHA string length
        $this->length = \mt_rand(5, 7); # random 5 or 6 or 7

        # CAPTCHA image colors (RGB, 0-255)
        //$this->foreground_color = array(0, 0, 0);
        //$background_color = array(220, 230, 255);
        $this->foreground_color = array(
            \mt_rand(0, 80),
            \mt_rand(0, 80),
            \mt_rand(0, 80)
        );
        $this->background_color = array(
            \mt_rand(220, 255),
            \mt_rand(220, 255),
            \mt_rand(220, 255)
        );

        $configurable_options = [
            'length',
            'width', 'height',
            'fluctuation_amplitude',
            'white_noise_density',
            'black_noise_density',
            'use_distortion',
            'no_spaces',
            'show_credits', 'credits',
            'foreground_color', 'background_color',
            'jpeg_quality', 'png_quality'
        ];

        foreach ($config as $key => $value) {
            if (\in_array($key, $configurable_options)) {
                $this->{$key} = $value;
            }
        }

        if (\array_key_exists('imageType', $config) && \in_array($config['imageType'], ['jpg', 'jpeg', 'gif', 'png'])) {
            $this->imageType = $config['imageType'];
        }

        $this->jpeg_quality = self::toRange($this->jpeg_quality, 1, 100);
        $this->png_quality = self::toRange($this->png_quality, -1, 9);

        $this->timing['init'] = \number_format(microtime(true) - $this->timing['current'], 5);
        $this->timing['current'] = \microtime(true);

        $this->loadFonts();

        $this->timing['loadfonts'] = \number_format(microtime(true) - $this->timing['current'], 5);
        $this->timing['current'] = \microtime(true);

        do {
            // generating random keystring
            while (true) {
                $this->keystring = '';
                for ($i = 0; $i < $this->length; $i++) {
                    $this->keystring .= $this->allowed_symbols[ \mt_rand(0, strlen($this->allowed_symbols) - 1) ];
                }
                if (!\preg_match('/cp|cb|ck|c6|c9|rn|rm|mm|co|do|cl|db|qp|qb|dp|ww/', $this->keystring)) {
                    break;
                }
            }

            $current_font_id = \mt_rand(0, $this->fonts_count - 1);
            $current_font = $this->fonts[ $current_font_id ];
            $font_metrics = $current_font['metrics'];
            $fontfile_height = $current_font['height'];
            $font = $current_font['resource'];

            $image_with_text_and_noise = \imagecreatetruecolor($this->width, $this->height);
            \imagealphablending($image_with_text_and_noise, true);
            $white = \imagecolorallocate($image_with_text_and_noise, 255, 255, 255);
            $black = \imagecolorallocate($image_with_text_and_noise, 0, 0, 0);

            \imagefilledrectangle($image_with_text_and_noise, 0, 0, $this->width - 1, $this->height - 1, $white);

            // draw text
            $x = 1;
            $odd = \mt_rand(0, 1);
            if ($odd == 0) {
                $odd = -1;
            }

            for ($i = 0; $i < $this->length; $i++) {
                $m = $font_metrics[$this->keystring[$i]]; // метрики нужной буквы шрифта

                $y = (($i % 2) * $this->fluctuation_amplitude - $this->fluctuation_amplitude / 2) * $odd
                    + \mt_rand(-\number_format($this->fluctuation_amplitude / 3), \number_format($this->fluctuation_amplitude / 3))
                    + ($this->height - $fontfile_height) / 2;

                if ($this->no_spaces) {
                    $shift = 0;
                    if ($i > 0) {
                        $shift = 10000;
                        for ($sy = 3; $sy < $fontfile_height - 10; $sy += 1) {
                            for ($sx = $m['start'] - 1; $sx < $m['end']; $sx += 1) {
                                $rgb = \imagecolorat($font, $sx, $sy);
                                $opacity = $rgb >> 24;
                                if ($opacity < 127) {
                                    $left = $sx - $m['start'] + $x;
                                    $py = $sy + $y;
                                    if ($py > $this->height) {
                                        break;
                                    }
                                    for ($px = \min($left, $this->width - 1); $px > $left - 200 && $px >= 0; $px -= 1) {
                                        $color = \imagecolorat($image_with_text_and_noise, $px, $py) & 0xff;
                                        if ($color + $opacity < 170) { // 170 - threshold
                                            if ($shift > $left - $px) {
                                                $shift = $left - $px;
                                            }
                                            break;
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                        if ($shift == 10000) {
                            $shift = \mt_rand(4, 6);
                        }

                    }
                } else {
                    $shift = 1;
                }
                // копируем букву из ресурса шрифта на изображение
                \imagecopy($image_with_text_and_noise, $font, $x - $shift, $y, $m['start'], 1, $m['end'] - $m['start'], $fontfile_height);
                $x += $m['end'] - $m['start'] - $shift;
            }
        } while ($x >= $this->width - 10); // while not fit in canvas

        $this->timing['copy_letters'] = \number_format(microtime(true) - $this->timing['current'], 5);
        $this->timing['current'] = \microtime(true);

        if ($this->white_noise_density > 0) {
            $white = \imagecolorallocate($font, 255, 255, 255);
            for ($i = 0; $i < (($this->height - 30) * $x) * $this->white_noise_density; $i++) {
                \imagesetpixel($image_with_text_and_noise, \mt_rand(0, $x - 1), \mt_rand(10, $this->height - 15), $white);
            }
        }

        $this->timing['white_noise'] = \number_format(\microtime(true) - $this->timing['current'], 5);
        $this->timing['current'] = \microtime(true);

        if ($this->black_noise_density > 0) {
            $black = \imagecolorallocate($font, 0, 0, 0);
            for ($i = 0; $i < (($this->height - 30) * $x) * $this->black_noise_density; $i++) {
                \imagesetpixel($image_with_text_and_noise, \mt_rand(0, $x - 1), \mt_rand(10, $this->height - 15), $black);
            }
        }
        $this->timing['black_noise'] = \number_format(\microtime(true) - $this->timing['current'], 5);
        $this->timing['current'] = \microtime(true);

        $center = $x / 2;

        // create final image

        $image = \imagecreatetruecolor($this->width, $this->height + ($this->show_credits ? 12 : 0));
        $foreground = \imagecolorallocate($image, $this->foreground_color[0], $this->foreground_color[1], $this->foreground_color[2]);
        $background = \imagecolorallocate($image, $this->background_color[0], $this->background_color[1], $this->background_color[2]);
        \imagefilledrectangle($image, 0, 0, $this->width - 1, $this->height - 1, $background);

        $this->timing['image2_ready'] = \number_format(\microtime(true) - $this->timing['current'], 5);
        $this->timing['current'] = \microtime(true);

        // credits. To remove, see configuration file
        if ($this->show_credits) {
            \imagefilledrectangle($image, 0, $this->height, $this->width - 1, $this->height + 12, $foreground);
            $credits = empty($this->credits) ? $_SERVER['HTTP_HOST'] : $this->credits;
            \imagestring($image, 2, $this->width / 2 - \imagefontwidth(2) * \strlen($credits) / 2, $this->height - 2, $credits, $background);
        }

        $this->timing['credits'] = \number_format(\microtime(true) - $this->timing['current'], 5);
        $this->timing['current'] = \microtime(true);

        //wave distortion
        $this->timing['before_distortion'] = \number_format(\microtime(true) - $this->timing['current'], 5);
        $this->timing['current'] = \microtime(true);

        if ($this->use_distortion) {
            // periods
            $period_rand1 = \mt_rand(750000, 1200000) / 10000000;
            $period_rand2 = \mt_rand(750000, 1200000) / 10000000;
            $period_rand3 = \mt_rand(750000, 1200000) / 10000000;
            $period_rand4 = \mt_rand(750000, 1200000) / 10000000;

            // phases
            $phase_rand5 = \mt_rand(0, 31415926) / 10000000;
            $phase_rand6 = \mt_rand(0, 31415926) / 10000000;
            $phase_rand7 = \mt_rand(0, 31415926) / 10000000;
            $phase_rand8 = \mt_rand(0, 31415926) / 10000000;

            // amplitudes
            $amplitude_rand9 = \mt_rand(330, 420) / 110;
            $ampliture_rand10 = \mt_rand(330, 450) / 100;

            for ($x = 0; $x < $this->width; $x++) {
                for ($y = 0; $y < $this->height; $y++) {
                    $sx = $x + (\sin($x * $period_rand1 + $phase_rand5) + \sin($y * $period_rand3 + $phase_rand6)) * $amplitude_rand9 - $this->width / 2 + $center + 1;
                    $sy = $y + (\sin($x * $period_rand2 + $phase_rand7) + \sin($y * $period_rand4 + $phase_rand8)) * $ampliture_rand10;

                    if ($sx < 0 || $sy < 0 || $sx >= $this->width - 1 || $sy >= $this->height - 1) {
                        continue;
                    } else {
                        $color = \imagecolorat($image_with_text_and_noise, $sx, $sy) & 0xFF;
                        $color_x = \imagecolorat($image_with_text_and_noise, $sx + 1, $sy) & 0xFF;
                        $color_y = \imagecolorat($image_with_text_and_noise, $sx, $sy + 1) & 0xFF;
                        $color_xy = \imagecolorat($image_with_text_and_noise, $sx + 1, $sy + 1) & 0xFF;
                    }

                    if ($color == 255 && $color_x == 255 && $color_y == 255 && $color_xy == 255) {
                        continue;
                    } else {
                        if ($color == 0 && $color_x == 0 && $color_y == 0 && $color_xy == 0) {
                            $newred = $this->foreground_color[0];
                            $newgreen = $this->foreground_color[1];
                            $newblue = $this->foreground_color[2];
                        } else {
                            $frsx = $sx - floor($sx);
                            $frsy = $sy - floor($sy);
                            $frsx1 = 1 - $frsx;
                            $frsy1 = 1 - $frsy;

                            $newcolor = (
                                $color * $frsx1 * $frsy1 +
                                $color_x * $frsx * $frsy1 +
                                $color_y * $frsx1 * $frsy +
                                $color_xy * $frsx * $frsy);

                            if ($newcolor > 255) {
                                $newcolor = 255;
                            }
                            $newcolor = $newcolor / 255;
                            $newcolor0 = 1 - $newcolor;

                            $newred = $newcolor0 * $this->foreground_color[0] + $newcolor * $this->background_color[0];
                            $newgreen = $newcolor0 * $this->foreground_color[1] + $newcolor * $this->background_color[1];
                            $newblue = $newcolor0 * $this->foreground_color[2] + $newcolor * $this->background_color[2];
                        }
                    }

                    \imagesetpixel($image, $x, $y, \imagecolorallocate($image, $newred, $newgreen, $newblue));
                }
            }
        } else {
            \imagecopy($image, $image_with_text_and_noise, 0, 0, 0, 0, $this->width, $this->height);
        }

        $this->timing['after_distortion'] = \number_format(\microtime(true) - $this->timing['current'], 5);
        $this->timing['current'] = \microtime(true);

        $this->image_resource = $image;
    }

    /**
     * Display captcha
     *
     * @param null $type
     * @return void
     */
    public function display($type = null):void
    {
        $image = $this->getImageResource();

        \header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        \header('Cache-Control: no-store, no-cache, must-revalidate');
        \header('Cache-Control: post-check=0, pre-check=0', false);
        \header('Pragma: no-cache');

        $outType = !is_null($type) ? $type : $this->imageType;

        switch ($outType) {
            case 'gif': {
                \header("Content-Type: image/gif");
                \imagegif($image);
                break;
            }
            case 'png': {
                \header("Content-Type: image/x-png");
                \imagepng($image, null, $this->png_quality);
                break;
            }
            case 'webp': {
                \header("Content-Type: image/webp");
                \imagewebp($image, null);
            }
            default: {
                \header("Content-Type: image/jpeg");
                \imagejpeg($image, null, $this->jpeg_quality);
                break;
            }
        }
    }

    //

    /**
     * returns keystring
     *
     * @return string
     */
    public function getKeyString():string
    {
        return $this->keystring;
    }

    /**
     * @return false|\GdImage|resource
     */
    public function &getImageResource()
    {
        return $this->image_resource;
    }

    /**
     * @param $value
     * @param $min
     * @param $max
     * @return mixed
     */
    private static function toRange($value, $min, $max)
    {
        return \max($min, \min($value, $max));
    }

    /**
     * Загружает шрифты
     *
     * @return void
     */
    private function loadFonts()
    {
        $available_font_files = \glob(__DIR__ . '/' . $this->fontsdir . '/*.png');

        foreach ($available_font_files as $index => $font_file) {

            $font = \imagecreatefrompng($font_file);
            \imagealphablending($font, true);

            $fontfile_width = \imagesx($font);
            $fontfile_height = \imagesy($font) - 1;

            $font_metrics = [];
            $symbol = 0;
            $reading_symbol = false;

            $alphabet_length = \strlen($this->alphabet);

            for ($i = 0; $i < $fontfile_width && $symbol < $alphabet_length; $i++) {
                $transparent = (\imagecolorat($font, $i, 0) >> 24) == 127;

                if (!$reading_symbol && !$transparent) {
                    $font_metrics[$this->alphabet[$symbol]] = array('start' => $i);
                    $reading_symbol = true;
                    continue;
                }

                if ($reading_symbol && $transparent) {
                    $font_metrics[$this->alphabet[$symbol]]['end'] = $i;
                    $reading_symbol = false;
                    $symbol++;
                    continue;
                }
            }

            $this->fonts[ $index ] = [
                'resource'      =>  $font,
                'width'         =>  $fontfile_width,
                'height'        =>  $fontfile_height,
                'metrics'       =>  $font_metrics
            ];

            $this->fonts_count++;
        }
    }
}

# -eof-
