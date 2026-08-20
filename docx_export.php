<?php
/**
 * Export selected vendors to announcement-style .docx (format like ปี 2568).
 * Uses SciZipArchive / ZipArchive — no external Word library.
 */

require_once __DIR__ . '/sci_zip.php';
require_once __DIR__ . '/xlsx_lib.php';

function sci_docx_xml_escape(string $text): string {
  return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** @return list<array{0:string,1:string}> title => regex */
function sci_docx_title_patterns(): array {
  return [
    ['จ.ส.ต.หญิง', '/^จ\.?\s*ส\.?\s*ต\.?\s*หญิง/u'],
    ['จ.ส.ต.', '/^จ\.?\s*ส\.?\s*ต\.?(?!\s*หญิง)/u'],
    ['นางสาว', '/^นางสาว/u'],
    ['น.ส.', '/^น\.?\s*ส\.?/u'],
    ['นาย', '/^นาย/u'],
    ['นาง', '/^นาง(?!สาว)/u'],
    ['ด.ช.', '/^(?:ด\.?\s*ช\.?|เด็กชาย)/u'],
    ['ด.ญ.', '/^(?:ด\.?\s*ญ\.?|เด็กหญิง)/u'],
  ];
}

function sci_docx_name_has_title(string $name): bool {
  $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
  if ($name === '') return false;
  foreach (sci_docx_title_patterns() as [, $re]) {
    if (preg_match($re, $name)) return true;
  }
  return false;
}

function sci_docx_extract_title(string $fullName): ?string {
  $name = trim(preg_replace('/\s+/u', ' ', $fullName) ?? '');
  foreach (sci_docx_title_patterns() as [$title, $re]) {
    if (preg_match($re, $name)) return $title;
  }
  return null;
}

function sci_docx_strip_title(string $name): string {
  $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
  foreach (sci_docx_title_patterns() as [, $re]) {
    $stripped = preg_replace($re, '', $name);
    if (is_string($stripped) && $stripped !== $name) {
      return trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');
    }
  }
  return $name;
}

function sci_docx_apply_title(string $name, string $title): string {
  $core = sci_docx_strip_title($name);
  $title = trim($title);
  if ($title === '' || $core === '') return sci_docx_clean_name($name);
  // ยศยาวเว้นวรรคหลังคำนำหน้า, คำนำหน้าทั่วไปติดชื่อ
  if (preg_match('/^(จ\.ส\.ต)/u', $title)) {
    return sci_docx_clean_name($title . ' ' . $core);
  }
  return sci_docx_clean_name($title . $core);
}

function sci_docx_clean_name(string $name): string {
  $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
  $name = preg_replace('/^จ\.?\s*ส\.?\s*ต\.?\s*หญิง\s+/u', 'จ.ส.ต.หญิง ', $name) ?? $name;
  $name = preg_replace('/^จ\.?\s*ส\.?\s*ต\.?\s+/u', 'จ.ส.ต. ', $name) ?? $name;
  $name = preg_replace('/^น\.ส\.?\s*/u', 'น.ส.', $name) ?? $name;
  $name = preg_replace('/^นางสาว\s+/u', 'นางสาว', $name) ?? $name;
  $name = preg_replace('/^นาง\s+/u', 'นาง', $name) ?? $name;
  $name = preg_replace('/^นาย\s+/u', 'นาย', $name) ?? $name;
  $name = preg_replace('/^ด\.?\s*ช\.?\s*/u', 'ด.ช.', $name) ?? $name;
  $name = preg_replace('/^ด\.?\s*ญ\.?\s*/u', 'ด.ญ.', $name) ?? $name;
  return $name;
}

function sci_docx_title_cache_path(): string {
  return sci_data_dir() . DIRECTORY_SEPARATOR . 'idcard_title_cache.json';
}

function sci_docx_load_title_cache(): array {
  $path = sci_docx_title_cache_path();
  if (!is_file($path)) return [];
  $raw = json_decode((string)file_get_contents($path), true);
  return is_array($raw) ? $raw : [];
}

function sci_docx_save_title_cache(array $cache): void {
  $path = sci_docx_title_cache_path();
  @file_put_contents(
    $path,
    json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
  );
}

/**
 * Ask Python OCR helper for titles from ID-card Drive files.
 *
 * @param list<array{key:string,file_id:string,core_name:string}> $items
 * @return array<string,string> key => title
 */
function sci_docx_ocr_titles_from_idcards(array $items): array {
  if (!$items) return [];
  $script = sci_dir() . DIRECTORY_SEPARATOR . 'idcard_title.py';
  if (!is_file($script)) return [];

  $payload = json_encode(['items' => array_values($items)], JSON_UNESCAPED_UNICODE);
  if ($payload === false) return [];

  $python = getenv('SCI_PYTHON') ?: 'python';
  $cmd = '"' . str_replace('"', '', $python) . '" ' . escapeshellarg($script);

  $descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
  ];
  $proc = @proc_open($cmd, $descriptors, $pipes, sci_dir());
  if (!is_resource($proc)) return [];

  fwrite($pipes[0], $payload);
  fclose($pipes[0]);
  stream_set_timeout($pipes[1], 240);
  $stdout = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);
  fclose($pipes[2]);
  proc_close($proc);
  unset($stderr);

  $out = json_decode((string)$stdout, true);
  if (!is_array($out) || !isset($out['results']) || !is_array($out['results'])) {
    return [];
  }
  $titles = [];
  foreach ($out['results'] as $key => $row) {
    if (!empty($row['ok']) && !empty($row['title'])) {
      $titles[(string)$key] = (string)$row['title'];
    }
  }
  return $titles;
}

/**
 * Resolve display name: keep existing title; otherwise add from ID card OCR (cached),
 * then alumni name as last resort.
 */
function sci_docx_resolve_display_name(array $applicant, array &$titleCache, array &$pendingOcr): string {
  $raw = trim((string)($applicant['name'] ?? ''));
  $cleaned = sci_docx_clean_name($raw);
  if (sci_docx_name_has_title($cleaned)) {
    return $cleaned;
  }

  $fileId = sci_drive_id((string)($applicant['id_card'] ?? '')) ?: '';
  $cacheKey = $fileId !== '' ? ('id:' . $fileId) : ('row:' . (string)($applicant['row'] ?? ''));

  if ($cacheKey !== '' && !empty($titleCache[$cacheKey]['title'])) {
    return sci_docx_apply_title($cleaned, (string)$titleCache[$cacheKey]['title']);
  }

  if ($fileId !== '') {
    $pendingOcr[$cacheKey] = [
      'key' => $cacheKey,
      'file_id' => $fileId,
      'core_name' => sci_docx_strip_title($cleaned),
    ];
  }

  // Keep without title for now; OCR + alumni fallback applied in second pass
  return $cleaned;
}

function sci_docx_slot_sort_key(string $slot): array {
  if (preg_match('/^([A-Za-z]+)(\d+)$/', trim($slot), $m)) {
    return [ord(strtoupper($m[1])), (int)$m[2], $slot];
  }
  return [99, 9999, $slot];
}

/**
 * Collect selected vendors for announcement (ปีปัจจุบัน).
 * เรียงตามล็อกจำหน่าย A1–A12 → B1–B6 → C1–C14 → D1–D8
 * ข้ามล็อกที่ยังไม่ถูกคัดเลือก
 * เติมคำนำหน้าจากบัตร ปชช. หากชื่อยังไม่มี
 *
 * @return list<array{name:string,category:string,slot:string}>
 */
function sci_selected_vendors_for_announcement(?array $data = null): array {
  @set_time_limit(300);
  $data = $data ?? sci_parse_applicants();
  $slotCat = [];
  foreach ($data['slots'] ?? [] as $s) {
    $slotCat[(string)($s['id'] ?? '')] = (string)($s['cat'] ?? '');
  }

  $titleCache = sci_docx_load_title_cache();
  $pendingOcr = [];
  $draft = [];

  foreach ($data['applicants'] ?? [] as $a) {
    if (($a['selection'] ?? '') !== 'ได้รับการคัดเลือก') continue;
    $slot = strtoupper(trim((string)($a['assigned_slot'] ?? '')));
    if ($slot === '') continue;

    $fileId = sci_drive_id((string)($a['id_card'] ?? '')) ?: '';
    $cacheKey = $fileId !== '' ? ('id:' . $fileId) : ('row:' . (string)($a['row'] ?? ''));

    $draft[] = [
      'applicant' => $a,
      'slot' => $slot,
      'category' => (($slotCat[$slot] ?? '') !== '') ? $slotCat[$slot] : trim((string)($a['category'] ?? '')),
      'cache_key' => $cacheKey,
      'name' => sci_docx_resolve_display_name($a, $titleCache, $pendingOcr),
    ];
  }

  // OCR only names still missing a title
  $needOcr = [];
  foreach ($draft as $i => $row) {
    if (sci_docx_name_has_title($row['name'])) continue;
    $key = $row['cache_key'];
    if (isset($pendingOcr[$key])) {
      $needOcr[$key] = $pendingOcr[$key];
    }
  }

  if ($needOcr) {
    $found = sci_docx_ocr_titles_from_idcards(array_values($needOcr));
    $now = date('c');
    foreach ($found as $key => $title) {
      $titleCache[$key] = [
        'title' => $title,
        'source' => 'id_card_ocr',
        'updated_at' => $now,
      ];
    }
    if ($found) {
      sci_docx_save_title_cache($titleCache);
    }
    foreach ($draft as $i => $row) {
      if (sci_docx_name_has_title($row['name'])) continue;
      $key = $row['cache_key'];
      if (!empty($found[$key])) {
        $core = (string)($row['applicant']['name'] ?? '');
        $draft[$i]['name'] = sci_docx_apply_title($core, $found[$key]);
        continue;
      }
      // Fallback: คำนำหน้าจากรายชื่อเจ้าเดิมปีก่อน (ถ้าตรงคน)
      $alumniName = trim((string)(($row['applicant']['alumni']['name'] ?? '') ?: ''));
      $alumniTitle = $alumniName !== '' ? sci_docx_extract_title($alumniName) : null;
      if ($alumniTitle) {
        $draft[$i]['name'] = sci_docx_apply_title((string)($row['applicant']['name'] ?? ''), $alumniTitle);
        if ($key !== '') {
          $titleCache[$key] = [
            'title' => $alumniTitle,
            'source' => 'alumni_fallback',
            'updated_at' => $now,
          ];
        }
      }
    }
    sci_docx_save_title_cache($titleCache);
  } else {
    // No OCR needed, still apply alumni fallback for any remaining untitled names
    $now = date('c');
    $changed = false;
    foreach ($draft as $i => $row) {
      if (sci_docx_name_has_title($row['name'])) continue;
      $alumniName = trim((string)(($row['applicant']['alumni']['name'] ?? '') ?: ''));
      $alumniTitle = $alumniName !== '' ? sci_docx_extract_title($alumniName) : null;
      if (!$alumniTitle) continue;
      $draft[$i]['name'] = sci_docx_apply_title((string)($row['applicant']['name'] ?? ''), $alumniTitle);
      $key = $row['cache_key'];
      if ($key !== '') {
        $titleCache[$key] = [
          'title' => $alumniTitle,
          'source' => 'alumni_fallback',
          'updated_at' => $now,
        ];
        $changed = true;
      }
    }
    if ($changed) sci_docx_save_title_cache($titleCache);
  }

  $rows = [];
  foreach ($draft as $row) {
    $rows[] = [
      'name' => $row['name'],
      'category' => $row['category'],
      'slot' => $row['slot'],
    ];
  }

  usort($rows, function ($a, $b) {
    return sci_docx_slot_sort_key($a['slot']) <=> sci_docx_slot_sort_key($b['slot']);
  });

  return $rows;
}

function sci_docx_paragraph(string $text, array $opts = []): string {
  $align = $opts['align'] ?? 'left'; // left|center
  $bold = !empty($opts['bold']);
  $size = (int)($opts['size'] ?? 32); // half-points (16pt = 32)
  $spaceAfter = (int)($opts['space_after'] ?? 60);
  $jc = $align === 'center' ? '<w:jc w:val="center"/>' : '';
  $b = $bold ? '<w:b/><w:bCs/>' : '';
  $xmlText = sci_docx_xml_escape($text);
  return '<w:p>'
    . '<w:pPr>' . $jc
    . '<w:spacing w:before="20" w:after="' . $spaceAfter . '" w:line="240" w:lineRule="auto"/>'
    . '</w:pPr>'
    . '<w:r><w:rPr>'
    . '<w:rFonts w:ascii="TH Sarabun New" w:hAnsi="TH Sarabun New" w:eastAsia="TH Sarabun New"/>'
    . $b
    . '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/>'
    . '</w:rPr><w:t xml:space="preserve">' . $xmlText . '</w:t></w:r>'
    . '</w:p>';
}

function sci_docx_table_cell(string $text, array $opts = []): string {
  $align = $opts['align'] ?? 'left';
  $bold = !empty($opts['bold']);
  $width = (int)($opts['width'] ?? 2000); // twips
  $size = (int)($opts['size'] ?? 30);
  $jc = $align === 'center' ? '<w:jc w:val="center"/>' : '';
  $b = $bold ? '<w:b/><w:bCs/>' : '';
  $xmlText = sci_docx_xml_escape($text);
  return '<w:tc>'
    . '<w:tcPr>'
    . '<w:tcW w:w="' . $width . '" w:type="dxa"/>'
    . '<w:tcBorders>'
    . '<w:top w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
    . '<w:left w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
    . '<w:bottom w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
    . '<w:right w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
    . '</w:tcBorders>'
    . '<w:vAlign w:val="center"/>'
    . '</w:tcPr>'
    . '<w:p><w:pPr>' . $jc
    . '<w:spacing w:before="40" w:after="40" w:line="240" w:lineRule="auto"/>'
    . '</w:pPr>'
    . '<w:r><w:rPr>'
    . '<w:rFonts w:ascii="TH Sarabun New" w:hAnsi="TH Sarabun New" w:eastAsia="TH Sarabun New"/>'
    . $b
    . '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/>'
    . '</w:rPr><w:t xml:space="preserve">' . $xmlText . '</w:t></w:r></w:p>'
    . '</w:tc>';
}

/**
 * Build .docx binary for selected announcement table.
 *
 * @param list<array{name:string,category:string,slot:string}>|null $rows
 */
function sci_build_selected_announcement_docx(?array $rows = null): string {
  $rows = $rows ?? sci_selected_vendors_for_announcement();
  $widths = [1200, 4200, 3600, 1600]; // twips ≈ 1.8+7.2+6.2+2.6 cm
  $headers = ['ลำดับ', 'ชื่อ - สกุล', 'ประเภทร้านค้า', 'ล็อกจำหน่าย'];

  $body = '';
  $body .= sci_docx_paragraph('รายชื่อผู้ประกอบการที่ได้รับการคัดเลือก', [
    'align' => 'center', 'bold' => true, 'size' => 36, 'space_after' => 40,
  ]);
  $body .= sci_docx_paragraph('สัปดาห์วิทยาศาสตร์แห่งชาติ ส่วนภูมิภาค', [
    'align' => 'center', 'bold' => true, 'size' => 32, 'space_after' => 0,
  ]);
  $roundMeta = sci_round_meta();
  $yearLine = 'ณ คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น ประจำปี ' . ($roundMeta['year'] ?? 2569);
  if (!empty($roundMeta['label'])) {
    $yearLine .= ' ' . $roundMeta['label'];
  }
  $body .= sci_docx_paragraph($yearLine, [
    'align' => 'center', 'bold' => true, 'size' => 32, 'space_after' => 200,
  ]);

  $tbl = '<w:tbl>'
    . '<w:tblPr>'
    . '<w:tblW w:w="10600" w:type="dxa"/>'
    . '<w:jc w:val="center"/>'
    . '<w:tblBorders>'
    . '<w:top w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
    . '<w:left w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
    . '<w:bottom w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
    . '<w:right w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
    . '<w:insideH w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
    . '<w:insideV w:val="single" w:sz="8" w:space="0" w:color="000000"/>'
    . '</w:tblBorders>'
    . '</w:tblPr>'
    . '<w:tblGrid>'
    . '<w:gridCol w:w="' . $widths[0] . '"/>'
    . '<w:gridCol w:w="' . $widths[1] . '"/>'
    . '<w:gridCol w:w="' . $widths[2] . '"/>'
    . '<w:gridCol w:w="' . $widths[3] . '"/>'
    . '</w:tblGrid>';

  $tbl .= '<w:tr>';
  foreach ($headers as $i => $h) {
    $tbl .= sci_docx_table_cell($h, [
      'bold' => true,
      'align' => 'center',
      'width' => $widths[$i],
      'size' => 32,
    ]);
  }
  $tbl .= '</w:tr>';

  foreach ($rows as $idx => $row) {
    $vals = [
      ($idx + 1) . '.',
      $row['name'],
      $row['category'],
      $row['slot'],
    ];
    $aligns = ['center', 'left', 'left', 'center'];
    $tbl .= '<w:tr>';
    foreach ($vals as $i => $v) {
      $tbl .= sci_docx_table_cell($v, [
        'align' => $aligns[$i],
        'width' => $widths[$i],
        'size' => 30,
      ]);
    }
    $tbl .= '</w:tr>';
  }
  $tbl .= '</w:tbl>';
  $body .= $tbl;

  $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas"'
    . ' xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"'
    . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
    . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
    . ' xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"'
    . ' xmlns:v="urn:schemas-microsoft-com:vml"'
    . ' xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing"'
    . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
    . ' xmlns:w10="urn:schemas-microsoft-com:office:word"'
    . ' xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
    . ' xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml"'
    . ' xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup"'
    . ' xmlns:wpi="http://schemas.microsoft.com/office/word/2010/wordprocessingInk"'
    . ' xmlns:wne="http://schemas.microsoft.com/office/word/2006/wordml"'
    . ' xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"'
    . ' mc:Ignorable="w14 wp14">'
    . '<w:body>'
    . $body
    . '<w:sectPr>'
    . '<w:pgSz w:w="11906" w:h="16838"/>'
    . '<w:pgMar w:top="1008" w:right="1008" w:bottom="1008" w:left="1008" w:header="708" w:footer="708" w:gutter="0"/>'
    . '</w:sectPr>'
    . '</w:body></w:document>';

  $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    . '</Types>';

  $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
    . '</Relationships>';

  $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';

  $tmp = tempnam(sys_get_temp_dir(), 'sci_docx_');
  if ($tmp === false) {
    throw new RuntimeException('สร้างไฟล์ชั่วคราวไม่สำเร็จ');
  }
  $path = $tmp . '.docx';
  @unlink($tmp);

  @unlink($path);
  $zip = sci_new_zip();
  $ok = $zip->open($path, class_exists('ZipArchive', false) ? (ZipArchive::CREATE | ZipArchive::OVERWRITE) : SciZipArchive::CREATE);
  if ($ok !== true) {
    throw new RuntimeException('เปิดไฟล์ ZIP สำหรับ .docx ไม่สำเร็จ');
  }

  $zip->addFromString('[Content_Types].xml', $contentTypes);
  $zip->addFromString('_rels/.rels', $rels);
  $zip->addFromString('word/document.xml', $documentXml);
  $zip->addFromString('word/_rels/document.xml.rels', $docRels);
  $zip->close();

  $bin = file_get_contents($path);
  @unlink($path);
  if ($bin === false || $bin === '') {
    throw new RuntimeException('อ่านไฟล์ .docx ไม่สำเร็จ');
  }
  return $bin;
}

/**
 * Stream announcement .docx download and exit.
 */
function sci_stream_selected_announcement_docx(): void {
  while (ob_get_level() > 0) {
    ob_end_clean();
  }

  $data = sci_parse_applicants();
  $rows = sci_selected_vendors_for_announcement($data);
  if (!$rows) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'ยังไม่มีร้านที่ได้รับการคัดเลือกและมีล็อกจำหน่าย'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $bin = sci_build_selected_announcement_docx($rows);
  $roundMeta = sci_round_meta();
  $roundTag = '_รอบ' . (int)$roundMeta['id'];
  $asciiTag = '_r' . (int)$roundMeta['id'];
  $filename = 'ประกาศร้านค้าที่ได้รับการคัดเลือก_2569' . $roundTag . '.docx';
  $asciiName = 'selected_vendors_announcement_2569' . $asciiTag . '.docx';

  header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
  header('Content-Length: ' . strlen($bin));
  header('Cache-Control: no-store');
  header(
    'Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($filename)
  );
  echo $bin;
  exit;
}
