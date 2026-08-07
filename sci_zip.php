<?php
/**
 * Minimal ZipArchive-compatible helper for hosts without ext-zip.
 * Supports Stored (0) and Deflate (8) entries. Requires zlib (gzinflate/gzdeflate).
 */
class SciZipArchive
{
  public const CREATE = 1;

  /** @var string|null */
  private $path = null;
  /** @var array<string,string> name => uncompressed content */
  private $files = [];
  private $dirty = false;

  public function open(string $filename, int $flags = 0): bool|int
  {
    $this->path = $filename;
    $this->files = [];
    $this->dirty = false;

    if (($flags & self::CREATE) && !is_file($filename)) {
      $this->dirty = true;
      return true;
    }

    if (!is_file($filename) || !is_readable($filename)) {
      return false;
    }

    try {
      $this->files = self::extractAll($filename);
      return true;
    } catch (Throwable $e) {
      return false;
    }
  }

  public function getFromName(string $name): string|false
  {
    $name = ltrim(str_replace('\\', '/', $name), '/');
    if (array_key_exists($name, $this->files)) {
      return $this->files[$name];
    }
    // case-insensitive fallback
    foreach ($this->files as $k => $v) {
      if (strcasecmp($k, $name) === 0) return $v;
    }
    return false;
  }

  public function addFromString(string $name, string $content): bool
  {
    $name = ltrim(str_replace('\\', '/', $name), '/');
    $this->files[$name] = $content;
    $this->dirty = true;
    return true;
  }

  public function close(): bool
  {
    if ($this->dirty && $this->path) {
      self::writeAll($this->path, $this->files);
    }
    $this->path = null;
    $this->files = [];
    $this->dirty = false;
    return true;
  }

  /** @return array<string,string> */
  private static function extractAll(string $path): array
  {
    if (!function_exists('gzinflate')) {
      throw new RuntimeException('ต้องการ PHP zlib (gzinflate) สำหรับอ่าน Excel โดยไม่ใช้ ZipArchive');
    }

    $bin = file_get_contents($path);
    if ($bin === false || strlen($bin) < 22) {
      throw new RuntimeException('ไฟล์ ZIP ว่างหรืออ่านไม่ได้');
    }

    $len = strlen($bin);
    $eocd = null;
    for ($i = $len - 22; $i >= 0 && $i >= $len - 65557; $i--) {
      if (substr($bin, $i, 4) === "PK\x05\x06") {
        $eocd = $i;
        break;
      }
    }
    if ($eocd === null) {
      throw new RuntimeException('ไม่พบ ZIP central directory');
    }

    $cdOffset = unpack('V', substr($bin, $eocd + 16, 4))[1];
    $cdEntries = unpack('v', substr($bin, $eocd + 10, 2))[1];

    $files = [];
    $pos = $cdOffset;
    for ($n = 0; $n < $cdEntries; $n++) {
      if (substr($bin, $pos, 4) !== "PK\x01\x02") {
        throw new RuntimeException('โครงสร้าง ZIP ไม่ถูกต้อง');
      }
      $method = unpack('v', substr($bin, $pos + 10, 2))[1];
      $compSize = unpack('V', substr($bin, $pos + 20, 4))[1];
      $uncompSize = unpack('V', substr($bin, $pos + 24, 4))[1];
      $nameLen = unpack('v', substr($bin, $pos + 28, 2))[1];
      $extraLen = unpack('v', substr($bin, $pos + 30, 2))[1];
      $commentLen = unpack('v', substr($bin, $pos + 32, 2))[1];
      $localOffset = unpack('V', substr($bin, $pos + 42, 4))[1];
      $name = substr($bin, $pos + 46, $nameLen);
      $pos += 46 + $nameLen + $extraLen + $commentLen;

      if (str_ends_with($name, '/')) {
        continue; // directory
      }

      if (substr($bin, $localOffset, 4) !== "PK\x03\x04") {
        throw new RuntimeException('local header เสียหาย: ' . $name);
      }
      $lNameLen = unpack('v', substr($bin, $localOffset + 26, 2))[1];
      $lExtraLen = unpack('v', substr($bin, $localOffset + 28, 2))[1];
      $dataStart = $localOffset + 30 + $lNameLen + $lExtraLen;
      $payload = substr($bin, $dataStart, $compSize);

      if ($method === 0) {
        $files[$name] = $payload;
      } elseif ($method === 8) {
        $raw = @gzinflate($payload);
        if ($raw === false) {
          throw new RuntimeException('แตกไฟล์ Deflate ไม่สำเร็จ: ' . $name);
        }
        $files[$name] = $raw;
      } else {
        throw new RuntimeException('ไม่รองรับ compression method ' . $method . ' ใน ' . $name);
      }

      // Prefer exact size when known
      if ($uncompSize > 0 && strlen($files[$name]) > $uncompSize) {
        $files[$name] = substr($files[$name], 0, $uncompSize);
      }
    }

    return $files;
  }

  /** @param array<string,string> $files */
  private static function writeAll(string $path, array $files): void
  {
    if (!function_exists('gzdeflate')) {
      throw new RuntimeException('ต้องการ PHP zlib (gzdeflate) สำหรับเขียน Excel โดยไม่ใช้ ZipArchive');
    }

    $now = getdate();
    $dosTime = (($now['hours'] & 0x1F) << 11) | (($now['minutes'] & 0x3F) << 5) | (int)floor($now['seconds'] / 2);
    $dosDate = ((($now['year'] - 1980) & 0x7F) << 9) | (($now['mon'] & 0xF) << 5) | ($now['mday'] & 0x1F);

    $local = '';
    $central = '';
    $offset = 0;
    $count = 0;

    foreach ($files as $name => $content) {
      $name = ltrim(str_replace('\\', '/', (string)$name), '/');
      if ($name === '' || str_ends_with($name, '/')) continue;

      $content = (string)$content;
      // Use unsigned CRC32 safely for pack('V')
      $crc = hexdec(hash('crc32b', $content));

      // Prefer Stored for reliability (Excel OOXML accepts it; avoids deflate quirks)
      $method = 0;
      $payload = $content;

      $compSize = strlen($payload);
      $uncompSize = strlen($content);
      $nameLen = strlen($name);

      $localHeader =
        "PK\x03\x04" .
        pack('v', 20) . // version needed
        pack('v', 0) .  // flags
        pack('v', $method) .
        pack('v', $dosTime) .
        pack('v', $dosDate) .
        pack('V', $crc) .
        pack('V', $compSize) .
        pack('V', $uncompSize) .
        pack('v', $nameLen) .
        pack('v', 0) . // extra
        $name .
        $payload;

      $centralHeader =
        "PK\x01\x02" .
        pack('v', 20) . // version made by
        pack('v', 20) . // version needed
        pack('v', 0) .
        pack('v', $method) .
        pack('v', $dosTime) .
        pack('v', $dosDate) .
        pack('V', $crc) .
        pack('V', $compSize) .
        pack('V', $uncompSize) .
        pack('v', $nameLen) .
        pack('v', 0) . // extra
        pack('v', 0) . // comment
        pack('v', 0) . // disk start
        pack('v', 0) . // int attr
        pack('V', 0) . // ext attr
        pack('V', $offset) .
        $name;

      $local .= $localHeader;
      $central .= $centralHeader;
      $offset += strlen($localHeader);
      $count++;
    }

    $cdOffset = strlen($local);
    $cdSize = strlen($central);
    $eocd =
      "PK\x05\x06" .
      pack('v', 0) .
      pack('v', 0) .
      pack('v', $count) .
      pack('v', $count) .
      pack('V', $cdSize) .
      pack('V', $cdOffset) .
      pack('v', 0);

    if (file_put_contents($path, $local . $central . $eocd) === false) {
      throw new RuntimeException('เขียนไฟล์ ZIP ไม่สำเร็จ');
    }
  }
}

/**
 * Prefer native ZipArchive when available; otherwise pure-PHP fallback.
 * @return ZipArchive|SciZipArchive
 */
function sci_new_zip()
{
  if (class_exists('ZipArchive', false)) {
    return new ZipArchive();
  }
  if (!class_exists('SciZipArchive', false)) {
    // already in this file
  }
  return new SciZipArchive();
}
