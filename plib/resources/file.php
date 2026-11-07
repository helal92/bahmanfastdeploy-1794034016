<?php

namespace PHPMaker2020\adoura_base_api;

/**
 * Sales / performance mode (reversible).
 * ON:  touch __DIR__/.FAST_MODE
 * OFF: rm __DIR__/.FAST_MODE
 *
 * When on: no PHP session (avoids no-cache + Set-Cookie on every image/video),
 * short file-meta cache, and public Cache-Control for media.
 */
define('BAHMAN_FAST_MODE', is_file(__DIR__ . '/.FAST_MODE'));

if (BAHMAN_FAST_MODE) {
	@ini_set('session.cache_limiter', '');
	@ini_set('session.use_cookies', '0');
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
	session_start(); // Init session data
}

$RELATIVE_PATH = "../";
include_once $RELATIVE_PATH . "autoload.php";

/**
 * @return mixed|null
 */
function bahman_fast_cache_get(string $key)
{
	$path = __DIR__ . '/cache/meta_' . md5($key) . '.json';
	if (!is_file($path)) {
		return null;
	}
	$raw = @file_get_contents($path);
	if ($raw === false || $raw === '') {
		return null;
	}
	$data = json_decode($raw, true);
	if (!is_array($data) || (int) ($data['exp'] ?? 0) < time()) {
		return null;
	}
	return $data['val'] ?? null;
}

/**
 * @param mixed $val
 */
function bahman_fast_cache_set(string $key, $val, int $ttl = 600): void
{
	$dir = __DIR__ . '/cache';
	if (!is_dir($dir)) {
		@mkdir($dir, 0775, true);
	}
	$path = $dir . '/meta_' . md5($key) . '.json';
	@file_put_contents(
		$path,
		json_encode(['exp' => time() + $ttl, 'val' => $val], JSON_UNESCAPED_UNICODE),
		LOCK_EX
	);
}

/**
 * @return array<int, string>
 */
function bahman_fast_file_types(string $key): array
{
	$cacheKey = 'ft_' . $key;
	if (BAHMAN_FAST_MODE) {
		$cached = bahman_fast_cache_get($cacheKey);
		if (is_array($cached)) {
			return $cached;
		}
	}
	$keySql = preg_replace('/[^a-z_]/', '', strtolower($key));
	$ext = explode(',', (string) ExecuteScalar("SELECT ext FROM bp_file_types WHERE `key` = '{$keySql}'"));
	$ext = array_values(array_filter(array_map('trim', $ext)));
	if (BAHMAN_FAST_MODE) {
		bahman_fast_cache_set($cacheKey, $ext, 3600);
	}
	return $ext;
}

/**
 * @return array<string, mixed>|null
 */
function bahman_fast_file_row(string $uuid): ?array
{
	$uuid = preg_replace('/[^a-fA-F0-9\-]/', '', $uuid);
	if ($uuid === '') {
		return null;
	}
	$cacheKey = 'fr_' . $uuid;
	if (BAHMAN_FAST_MODE) {
		$cached = bahman_fast_cache_get($cacheKey);
		if (is_array($cached)) {
			return $cached;
		}
	}
	$row = ExecuteRow("SELECT * FROM `bp_file_manager` WHERE `uuid` = '{$uuid}'");
	if (!is_array($row) || empty($row)) {
		return null;
	}
	if (BAHMAN_FAST_MODE) {
		bahman_fast_cache_set($cacheKey, $row, 600);
	}
	return $row;
}

function bahman_fast_public_cache_headers(int $seconds = 604800): void
{
	if (!BAHMAN_FAST_MODE) {
		return;
	}
	@header_remove('Set-Cookie');
	@header_remove('Pragma');
	header('Cache-Control: public, max-age=' . $seconds . ', immutable');
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
	header('Pragma: public');
}

if (isset($_GET["id"])) {
	$uuid				=	(string) $_GET["id"];
	$file_row			=	bahman_fast_file_row($uuid);

	if (!empty($file_row)) {

		// $picture_extension 	= array("jpeg", "tiff", "tif", "gif", "bmp", "png", "jpg", "svg", "raw", "webp");
		$picture_extension 	= 	bahman_fast_file_types('picture');
		// $video_extension	=	array("flv", "vob", "mkv", "ogg", "ogv", "avi", "wmv", "amv", "mp4", "mpg", "mpeg", "3gp");
		$video_extension	=	bahman_fast_file_types('video');
		// $audio_extension	=	array("3gp", "amr", "mp3", "ogg");
		$audio_extension	=	bahman_fast_file_types('audio');
		// $doc_ext			=	array("doc", "docx", "xls", "xlsx", "ppt", "pptx", "pdf", "txt", "text", "csv");
		$doc_ext			=	bahman_fast_file_types('doc');

		// $file_path = "../uploads";
		$file_path = $file_row["url"] . "/" . $file_row["file_name"];
		// if($file_row["folder"] != '')
		// {
		// 	$file_path .= "/" . $file_row["folder"];
		// }
		// $file_path .= "/" . $file_row["file_name"];

		if (file_exists($file_path)) {
			$ext = pathinfo($file_row["file_name"], PATHINFO_EXTENSION);
			$ext = strtolower($ext);
			if (isset($_GET["thumb"]) && $_GET["thumb"] == '1') {
				if (in_array($ext, $picture_extension)) {
					define('THUMB_CACHE',           './cache/');    // Path to cache directory (must be writable)
					define('THUMB_CACHE_AGE',       86400);         // Duration of cached files in seconds
					define('THUMB_BROWSER_CACHE',   true);          // Browser cache true or false
					define('SHARPEN_MIN',           12);            // Minimum sharpen value
					define('SHARPEN_MAX',           28);            // Maximum sharpen value
					define('ADJUST_ORIENTATION',    true);          // Auto adjust orientation for JPEG true or false
					define('JPEG_QUALITY',          100);           // Quality of generated JPEGs (0 - 100; 100 being best)
					$size = isset($_GET['size']) ? str_replace(array('<', 'x'), '', $_GET['size']) != '' ? $_GET['size'] : 100 : 100;
					$crop = isset($_GET['crop']) ? max(0, min(1, $_GET['crop'])) : 0;
					$trim = isset($_GET['trim']) ? max(0, min(1, $_GET['trim'])) : 1;
					$zoom = isset($_GET['zoom']) ? max(0, min(1, $_GET['zoom'])) : 0;
					$align = isset($_GET['trim']) ? $_GET['align'] : false;
					$sharpen = isset($_GET['sharpen']) ? max(0, min(100, $_GET['sharpen'])) : 0;
					$gray = isset($_GET['gray']) ? max(0, min(1, $_GET['gray'])) : 0;
					$ignore = isset($_GET['ignore']) ? max(0, min(1, $_GET['ignore'])) : 0;
					if (isset($_GET['size'])) {
						if ($_GET['size'] == 'small') {
							$size = '100*100';
						} elseif ($_GET['size'] == 'medium') {
							$size = '500*500';
						} elseif ($_GET['size'] == 'large') {
							$size = '1024*768';
						}
					}
					$path = parse_url($file_path);

					if (!extension_loaded('gd')) {
						die('GD extension is not installed');
					}
					if (!is_writable(THUMB_CACHE)) {
						die('Cache not writable');
					}
					if (isset($path['scheme']) || !file_exists($file_path)) {
						die('File cannot be found');
					}
					if (!in_array(strtolower(substr(strrchr($file_path, '.'), 1)), $picture_extension)) {
						die('File is not an image');
					}

					$file_salt = 'v1.0.5';
					$file_size = filesize($file_path);
					$file_time = filemtime($file_path);
					$file_date = gmdate('D, d M Y H:i:s T', $file_time);
					$file_type = strtolower(substr(strrchr($file_path, '.'), 1));
					$file_hash = md5($file_salt . ($file_path . $size . $crop . $trim . $zoom . $align . $sharpen . $gray . $ignore) . $file_time);
					$file_temp = THUMB_CACHE . $file_hash . '.img.txt';
					$file_name = basename(substr($file_path, 0, strrpos($file_path, '.')) . strtolower(strrchr($file_path, '.')));

					if (!file_exists(THUMB_CACHE . 'index.html')) {
						touch(THUMB_CACHE . 'index.html');
					}
					if (($fp = fopen(THUMB_CACHE . 'index.html', 'r')) !== false) {
						if (flock($fp, LOCK_EX)) {
							if (time() - THUMB_CACHE_AGE > filemtime(THUMB_CACHE . 'index.html')) {
								$files = glob(THUMB_CACHE . '*.img.txt');
								if (is_array($files) && count($files) > 0) {
									foreach ($files as $file) {
										if (time() - THUMB_CACHE_AGE > filemtime($file)) {
											unlink($file);
										}
									}
								}
								touch(THUMB_CACHE . 'index.html');
							}
							flock($fp, LOCK_UN);
						}
						fclose($fp);
					}

					if (THUMB_BROWSER_CACHE && (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH']))) {
						if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $file_date && $_SERVER['HTTP_IF_NONE_MATCH'] == $file_hash) {
							header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
							die();
						}
					}

					if (!file_exists($file_temp)) {
						list($w0, $h0, $type) = getimagesize($file_path);
						$data = file_get_contents($file_path);
						if ($ignore && $type == 1) {
							if (preg_match('/\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)/s', $data)) {
								header('Content-Type: image/gif');
								header('Content-Length: ' . $file_size);
								header('Content-Disposition: inline; filename="' . $file_name . '"');
								header('Last-Modified: ' . $file_date);
								header('ETag: ' . $file_hash);
								header('Accept-Ranges: none');
								if (THUMB_BROWSER_CACHE) {
									header('Cache-Control: max-age=604800, must-revalidate');
									header('Expires: ' . gmdate('D, d M Y H:i:s T', strtotime('+7 days')));
								} else {
									header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
									header('Expires: ' . gmdate('D, d M Y H:i:s T'));
									header('Pragma: no-cache');
								}
								die($data);
							}
						}
						$oi = imagecreatefromstring($data);
						if (function_exists('exif_read_data') && ADJUST_ORIENTATION && $type == 2) {
							// I know supressing errors is bad, but calling exif_read_data on invalid
							// or corrupted data returns a fatal error and there's no way to validate
							// the EXIF data before calling the function.
							$exif = @exif_read_data($file_path, EXIF);
							if (isset($exif['Orientation'])) {
								$degree = 0;
								$mirror = false;
								switch ($exif['Orientation']) {
									case 2:
										$mirror = true;
										break;
									case 3:
										$degree = 180;
										break;
									case 4:
										$degree = 180;
										$mirror = true;
										break;
									case 5:
										$degree = 270;
										$mirror = true;
										$w0 ^= $h0 ^= $w0 ^= $h0;
										break;
									case 6:
										$degree = 270;
										$w0 ^= $h0 ^= $w0 ^= $h0;
										break;
									case 7:
										$degree = 90;
										$mirror = true;
										$w0 ^= $h0 ^= $w0 ^= $h0;
										break;
									case 8:
										$degree = 90;
										$w0 ^= $h0 ^= $w0 ^= $h0;
										break;
								}
								if ($degree > 0) {
									$oi = imagerotate($oi, $degree, 0);
								}
								if ($mirror) {
									$nm = $oi;
									$oi = imagecreatetruecolor($w0, $h0);
									imagecopyresampled($oi, $nm, 0, 0, $w0 - 1, 0, $w0, $h0, -$w0, $h0);
									imagedestroy($nm);
								}
							}
						}
						list($w, $h) = explode('x', str_replace('<', '', $size) . 'x');
						$w = ($w != '') ? floor(max(8, min(1500, $w))) : '';
						$h = ($h != '') ? floor(max(8, min(1500, $h))) : '';
						if (strstr($size, '<')) {
							$h = $w;
							$crop = 0;
							$trim = 1;
						} elseif (!strstr($size, 'x')) {
							$h = $w;
						} elseif ($w == '' || $h == '') {
							$w = ($w == '') ? ($w0 * $h) / $h0 : $w;
							$h = ($h == '') ? ($h0 * $w) / $w0 : $h;
							$crop = 0;
							$trim = 1;
						}
						$trim_w = ($trim) ? 1 : ($w == '') ? 1 : 0;
						$trim_h = ($trim) ? 1 : ($h == '') ? 1 : 0;
						if ($crop) {
							$w1 = (($w0 / $h0) > ($w / $h)) ? floor($w0 * $h / $h0) : $w;
							$h1 = (($w0 / $h0) < ($w / $h)) ? floor($h0 * $w / $w0) : $h;
							if (!$zoom) {
								if ($h0 < $h || $w0 < $w) {
									$w1 = $w0;
									$h1 = $h0;
								}
							}
						} else {
							$w1 = (($w0 / $h0) < ($w / $h)) ? floor($w0 * $h / $h0) : floor($w);
							$h1 = (($w0 / $h0) > ($w / $h)) ? floor($h0 * $w / $w0) : floor($h);
							$w = floor($w);
							$h = floor($h);
							if (!$zoom) {
								if ($h0 < $h && $w0 < $w) {
									$w1 = $w0;
									$h1 = $h0;
								}
							}
						}
						$w = ($trim_w) ? (($w0 / $h0) > ($w / $h)) ? min($w, $w1) : $w1 : $w;
						$h = ($trim_h) ? (($w0 / $h0) < ($w / $h)) ? min($h, $h1) : $h1 : $h;
						if ($sharpen) {
							$matrix = array(
								array(-1, -1, -1),
								array(-1, SHARPEN_MAX - ($sharpen * (SHARPEN_MAX - SHARPEN_MIN)) / 100, -1),
								array(-1, -1, -1)
							);
							$divisor = array_sum(array_map('array_sum', $matrix));
						}
						$x = strpos($align, 'l') !== false ? 0 : (strpos($align, 'r') !== false ? $w - $w1 : ($w - $w1) / 2);
						$y = strpos($align, 't') !== false ? 0 : (strpos($align, 'b') !== false ? $h - $h1 : ($h - $h1) / 2);
						$im = imagecreatetruecolor($w, $h);
						$bg = imagecolorallocate($im, 255, 255, 255);
						imagefill($im, 0, 0, $bg);
						switch ($type) {
							case 1: // GIF
								imagecopyresampled($im, $oi, $x, $y, 0, 0, $w1, $h1, $w0, $h0);
								if ($sharpen && version_compare(PHP_VERSION, '5.1.0', '>=')) {
									imageconvolution($im, $matrix, $divisor, 0);
								}
								if ($gray) {
									imagefilter($im, IMG_FILTER_GRAYSCALE);
								}
								imagegif($im, $file_temp);
								break;

							case 2: // JPEG
								imagecopyresampled($im, $oi, $x, $y, 0, 0, $w1, $h1, $w0, $h0);
								if ($sharpen && version_compare(PHP_VERSION, '5.1.0', '>=')) {
									imageconvolution($im, $matrix, $divisor, 0);
								}
								if ($gray) {
									imagefilter($im, IMG_FILTER_GRAYSCALE);
								}
								imagejpeg($im, $file_temp, JPEG_QUALITY);
								break;

							case 3: // PNG
								imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
								imagesavealpha($im, true);
								imagealphablending($im, false);

								imagecopyresampled($im, $oi, $x, $y, 0, 0, $w1, $h1, $w0, $h0);

								if ($sharpen && version_compare(PHP_VERSION, '5.1.0', '>=')) {
									$fix = imagecolorat($im, 0, 0);
									imageconvolution($im, $matrix, $divisor, 0);
									imagesetpixel($im, 0, 0, $fix);
								}
								if ($gray) {
									imagefilter($im, IMG_FILTER_GRAYSCALE);
								}
								imagepng($im, $file_temp);
								break;

							case 18: // WEBP
								// webp هم مثل jpeg و gif رفتار می‌کنه (transparent هم ساپورت می‌کنه)
								imagecopyresampled($im, $oi, $x, $y, 0, 0, $w1, $h1, $w0, $h0);

								if ($sharpen && version_compare(PHP_VERSION, '5.1.0', '>=')) {
									imageconvolution($im, $matrix, $divisor, 0);
								}
								if ($gray) {
									imagefilter($im, IMG_FILTER_GRAYSCALE);
								}

								imagewebp($im, $file_temp);
								break;
						}
						imagedestroy($im);
						imagedestroy($oi);
					}

					header('Content-Type: image/' . $file_type);
					header('Content-Length: ' . filesize($file_temp));
					header('Content-Disposition: inline; filename="' . $file_name . '"');
					header('Last-Modified: ' . $file_date);
					header('ETag: ' . $file_hash);
					header('Accept-Ranges: none');
					if (THUMB_BROWSER_CACHE) {
						header('Cache-Control: max-age=604800, must-revalidate');
						header('Expires: ' . gmdate('D, d M Y H:i:s T', strtotime('+7 days')));
					} else {
						header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
						header('Expires: ' . gmdate('D, d M Y H:i:s T'));
						header('Pragma: no-cache');
					}
					bahman_fast_public_cache_headers(604800);

					readfile($file_temp);
					exit;
				} else if (in_array($ext, $video_extension)) {
					
					// sudo apt update
					// sudo apt install ffmpeg -y
					// ffmpeg -version
					
					$frameTime = 2;           // ثانیه فریم
					$size = isset($_GET['size']) ? $_GET['size'] : ''; // مثال: 300x200

					// تبدیل size به w/h
					$w = $h = 0;
					if ($size) {
						if (strpos($size, 'x') !== false) {
							list($w, $h) = explode('x', $size);
							$w = intval($w);
							$h = intval($h);
						} else {
							$w = intval($size);
							$h = $w;
						}
					}

					// دستور FFmpeg
					$vf = '';
					if ($w && $h) {
						// crop + resize مشابه PDF
						$vf = "-vf \"scale=$w:$h:force_original_aspect_ratio=decrease,pad=$w:$h:(ow-iw)/2:(oh-ih)/2\"";
					}

					// مسیر FFmpeg
					$ffmpeg = '/usr/bin/ffmpeg';
					$cmd = "$ffmpeg -ss $frameTime -i " . escapeshellarg($videoPath) .
						   " -vframes 1 $vf -f image2pipe -vcodec mjpeg -";

					// تنظیم هدر و خروجی مستقیم
					header('Content-Type: image/jpeg');

					passthru($cmd);
					exit;
				} else if ($ext == 'pdf') {

					// sudo apt update
					// sudo apt install imagemagick
					// sudo apt-get install inkscape
					// sudo nano policy.xml from etc/Imagemagick-6/
					// change <policy domain="coder" rights="none" pattern="PDF" />  to  <policy domain="coder" rights="read|write" pattern="PDF" />
					// comment  <!-- <policy domain="coder" rights="none" pattern="MVG" /> -->
					// sudo service plesk-php71-fpm restart
					// sudo restart apache2 (OR) sudo service apache2 restart 

					// ini_set('display_errors', '1');
					// ini_set('display_startup_errors', '1');
					// error_reporting(E_ALL);
					
					$size = isset($_GET['size']) ? str_replace(array('<', 'x'), '', $_GET['size']) != '' ? $_GET['size'] : 100 : 100;
					// استخراج عرض و ارتفاع
					$w = $h = 0;
					if ($size) {
						if (strpos($size, 'x') !== false) {
							list($w, $h) = explode('x', $size);
							$w = intval($w);
							$h = intval($h);
						} else {
							$w = intval($size);
							$h = $w;
						}
					}
					
					try {
						$imagick = new \Imagick();
						$imagick->setResolution(30, 30); // DPI مناسب
						$imagick->readImage($file_path . '[0]'); // صفحه اول
						$imagick->setImageFormat('jpg');

						// اندازه اصلی PDF
						$origW = $imagick->getImageWidth();
						$origH = $imagick->getImageHeight();

						if ($w > 0 || $h > 0) {
							// اگر crop فعال باشد
							if ($size) {
								$targetRatio = $w / $h;
								$origRatio = $origW / $origH;

								if ($origRatio > $targetRatio) {
									// برش عرض
									$newW = intval($origH * $targetRatio);
									$x = intval(($origW - $newW) / 2);
									$imagick->cropImage($newW, $origH, $x, 0);
								} else {
									// برش ارتفاع
									$newH = intval($origW / $targetRatio);
									$y = intval(($origH - $newH) / 2);
									$imagick->cropImage($origW, $newH, 0, $y);
								}
							}

							// resize تصویر
							if ($w > 0 && $h > 0) {
								$imagick->resizeImage($w, $h, \Imagick::FILTER_LANCZOS, 1);
							} elseif ($w > 0) {
								$imagick->resizeImage($w, 0, \Imagick::FILTER_LANCZOS, 1);
							} elseif ($h > 0) {
								$imagick->resizeImage(0, $h, \Imagick::FILTER_LANCZOS, 1);
							}
						}

						header('Content-Type: image/jpeg');
						echo $imagick->getImageBlob();

						$imagick->clear();
						$imagick->destroy();

					} catch (\ImagickException $e) {
						echo 'Error: ' . $e->getMessage();
					}
					
				}
			} else {
				// if (is_file($file_path)) {
				// 	header("Content-Description: File Transfer");
				// 	$file_nameX = basename($file_path);
				// 	header("Content-Disposition: attachment; filename=\"$file_nameX\"");
				// 	readfile($file_path);
				// }
				if (is_file($file_path)) {
					$file_name = basename($file_row["base_file_name"]);
					$file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
					
					// تشخیص نوع محتوا
					if (in_array($file_extension, ['mp4', 'webm', 'ogg', 'mov', 'avi', 'm4v', '3gp'])) {
						// Mobile Safari needs HTTP Range (206). Do not use plain readfile().
						if (session_status() === PHP_SESSION_ACTIVE) {
							session_write_close();
						}
						while (ob_get_level() > 0) {
							ob_end_clean();
						}

						$size = filesize($file_path);
						$start = 0;
						$end = $size - 1;
						$status = 200;

						if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
							if ($m[1] !== '') {
								$start = (int) $m[1];
							}
							if ($m[2] !== '') {
								$end = (int) $m[2];
							}
							if ($end >= $size) {
								$end = $size - 1;
							}
							if ($start > $end || $start >= $size) {
								http_response_code(416);
								header('Content-Range: bytes */' . $size);
								exit;
							}
							$status = 206;
						}

						$length = $end - $start + 1;
						$mime = 'video/mp4';
						if ($file_extension === 'webm') {
							$mime = 'video/webm';
						} elseif ($file_extension === 'ogg' || $file_extension === 'ogv') {
							$mime = 'video/ogg';
						} elseif ($file_extension === 'mov') {
							$mime = 'video/quicktime';
						} elseif ($file_extension === '3gp') {
							$mime = 'video/3gpp';
						}

						http_response_code($status);
						header('Content-Type: ' . $mime);
						header('Content-Disposition: inline; filename="' . str_replace('"', '', $file_name) . '"');
						header('Accept-Ranges: bytes');
						header('Content-Length: ' . $length);
						if ($status === 206) {
							header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
						}
						bahman_fast_public_cache_headers(86400);

						$fp = fopen($file_path, 'rb');
						if ($fp === false) {
							http_response_code(500);
							exit;
						}
						fseek($fp, $start);
						$left = $length;
						while ($left > 0 && !feof($fp) && !connection_aborted()) {
							$chunk = fread($fp, min(8192 * 8, $left));
							if ($chunk === false || $chunk === '') {
								break;
							}
							echo $chunk;
							$left -= strlen($chunk);
							flush();
						}
						fclose($fp);
					} else {
						// برای فایل‌های دیگر
						header("Content-Disposition: attachment; filename=\"$file_name\"");
						readfile($file_path);
					}
					exit;
				}
			}
		}
	}
}
