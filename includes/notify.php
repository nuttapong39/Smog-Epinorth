<?php
// notify.php — MOPH ALERT LINE Flex Message (Modern Status Card design)
// Silent-fail: any error is logged, never thrown

// Iconify PNG endpoint — always available, colored, 128px
// Format: https://api.iconify.design/{prefix}:{name}.png?color=%23xxxxxx&width=N
function _icon($prefix_name, $color = '3b82f6', $size = 96) {
    return 'https://api.iconify.design/' . $prefix_name . '.png?color=%23' . ltrim($color, '#') . '&width=' . $size;
}

function buildFlexMessage($hospitalName, $status, $data) {
    $isSuccess = ($status === 'success');

    // Palette: Sky Blue → Indigo (matches web theme)
    $accent    = $isSuccess ? '#10b981' : '#ef4444';
    $accentBg  = $isSuccess ? '#ECFDF5' : '#FEF2F2';
    $accentDim = $isSuccess ? '#065f46' : '#991b1b';

    $statusText  = $isSuccess ? 'ส่งข้อมูลสำเร็จ' : 'ส่งข้อมูลไม่สำเร็จ';
    $bigIcon     = $isSuccess ? _icon('mdi:check-decagram', '10b981', 200) : _icon('mdi:close-octagon', 'ef4444', 200);

    $dateStart = $data['date_start']    ?? '-';
    $dateEnd   = $data['date_end']      ?? '-';
    $records   = $data['total_records'] ?? 0;
    $syncedAt  = $data['synced_at']     ?? date('Y-m-d H:i:s');
    $errorMsg  = $data['error_msg']     ?? null;

    // Thai date
    $thaiMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $tsSynced   = strtotime($syncedAt);
    $dateThai   = (int)date('j', $tsSynced) . ' ' . $thaiMonths[(int)date('n', $tsSynced)] . ' ' . ((int)date('Y', $tsSynced) + 543);
    $timeThai   = date('H:i', $tsSynced);

    $rangeThai = $dateStart === $dateEnd
        ? $dateStart
        : $dateStart . ' → ' . $dateEnd;

    // ─── HEADER (sky-blue gradient banner) ────────────────
    $header = [
        'type' => 'box', 'layout' => 'vertical',
        'backgroundColor' => '#3b82f6',
        'paddingAll' => '20px',
        'contents' => [
            [
                'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'md',
                'contents' => [
                    [
                        'type' => 'image',
                        'url'  => _icon('mdi:hospital-building', 'ffffff', 96),
                        'size' => 'xxs', 'flex' => 0,
                    ],
                    [
                        'type' => 'text', 'text' => $hospitalName,
                        'color' => '#ffffff', 'weight' => 'bold', 'size' => 'lg',
                        'gravity' => 'center', 'wrap' => true,
                    ],
                ],
            ],
            [
                'type' => 'text', 'text' => 'Smog-Epinorth Data Sync',
                'color' => '#DBEAFE', 'size' => 'xs', 'margin' => 'sm',
            ],
        ],
    ];

    // ─── HERO STATUS ICON (large, centered) ────────────────
    $hero = [
        'type' => 'box', 'layout' => 'vertical',
        'paddingTop' => '28px', 'paddingBottom' => '12px',
        'contents' => [
            [
                'type' => 'image', 'url' => $bigIcon,
                'size' => 'xxl', 'aspectMode' => 'fit',
                'align' => 'center',
            ],
            [
                'type' => 'text', 'text' => $statusText,
                'align' => 'center', 'weight' => 'bold', 'size' => 'xl',
                'color' => $accent, 'margin' => 'md',
            ],
        ],
    ];

    // Info row helper — icon + label + value
    $infoRow = function ($iconName, $iconColor, $label, $value) {
        return [
            'type' => 'box', 'layout' => 'horizontal', 'margin' => 'lg', 'spacing' => 'md',
            'contents' => [
                [
                    'type' => 'image', 'url' => _icon($iconName, $iconColor, 64),
                    'size' => 'xxs', 'flex' => 0, 'aspectMode' => 'fit', 'gravity' => 'center',
                ],
                [
                    'type' => 'box', 'layout' => 'vertical', 'flex' => 1,
                    'contents' => [
                        ['type' => 'text', 'text' => $label, 'size' => 'xxs', 'color' => '#94A3B8'],
                        ['type' => 'text', 'text' => $value, 'size' => 'sm', 'color' => '#1E293B', 'weight' => 'bold', 'wrap' => true, 'margin' => 'xs'],
                    ],
                ],
            ],
        ];
    };

    // ─── INFO GRID ──────────────────────────────────────
    $bodyContents = [
        $hero,
        ['type' => 'separator', 'margin' => 'xl', 'color' => '#E2E8F0'],
        [
            'type' => 'box', 'layout' => 'vertical', 'paddingAll' => '4px', 'margin' => 'md',
            'contents' => [
                $infoRow('mdi:calendar-clock', '3b82f6', 'ช่วงข้อมูล',    $rangeThai),
                $infoRow('mdi:database-arrow-up', '6366f1', 'จำนวน Records', number_format($records) . ' รายการ'),
                $infoRow('mdi:clock-check',   '10b981', 'เวลาที่ส่ง',      $dateThai . ' · ' . $timeThai . ' น.'),
            ],
        ],
    ];

    // ─── ERROR BOX (if failed) ─────────────────────────
    if (!$isSuccess && $errorMsg) {
        $bodyContents[] = ['type' => 'separator', 'margin' => 'xl', 'color' => '#FECACA'];
        $bodyContents[] = [
            'type' => 'box', 'layout' => 'vertical',
            'backgroundColor' => $accentBg,
            'cornerRadius'    => '10px',
            'paddingAll'      => '14px',
            'margin'          => 'lg',
            'contents' => [
                [
                    'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm',
                    'contents' => [
                        ['type'=>'image', 'url'=>_icon('mdi:alert-circle', 'ef4444', 64), 'size'=>'xxs', 'flex'=>0, 'gravity'=>'center'],
                        ['type'=>'text', 'text'=>'สาเหตุความล้มเหลว', 'size'=>'xs', 'color'=>$accentDim, 'weight'=>'bold', 'gravity'=>'center'],
                    ],
                ],
                [
                    'type' => 'text',
                    'text' => mb_substr($errorMsg, 0, 200),
                    'size' => 'xs', 'color' => $accentDim, 'wrap' => true, 'margin' => 'sm',
                ],
            ],
        ];
    }

    $body = [
        'type' => 'box', 'layout' => 'vertical',
        'paddingAll' => '20px', 'backgroundColor' => '#FFFFFF',
        'contents' => $bodyContents,
    ];

    // ─── FOOTER ─────────────────────────────────────────
    $footer = [
        'type' => 'box', 'layout' => 'vertical',
        'backgroundColor' => '#F8FAFC',
        'paddingAll' => '14px',
        'contents' => [
            [
                'type' => 'box', 'layout' => 'horizontal', 'spacing' => 'sm',
                'contents' => [
                    ['type'=>'image', 'url'=>_icon('mdi:shield-check', '3b82f6', 48), 'size'=>'xxs', 'flex'=>0, 'gravity'=>'center'],
                    ['type'=>'text', 'text'=>'MOPH ALERT · ระบบเฝ้าระวังหมอกควัน', 'size'=>'xxs', 'color'=>'#64748B', 'gravity'=>'center'],
                ],
            ],
        ],
    ];

    return [
        [
            'type' => 'flex',
            'altText' => $hospitalName . ' — ' . $statusText,
            'contents' => [
                'type'   => 'bubble',
                'size'   => 'mega',
                'header' => $header,
                'body'   => $body,
                'footer' => $footer,
                'styles' => [
                    'body'   => ['backgroundColor' => '#FFFFFF'],
                    'footer' => ['separator' => true, 'separatorColor' => '#E2E8F0'],
                ],
            ],
        ],
    ];
}

function sendMophNotification($hospitalName, $status, $data) {
    try {
        $messages = buildFlexMessage($hospitalName, $status, $data);
        $payload  = json_encode(['messages' => $messages], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(MOPH_NOTIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'client-key: ' . (function_exists('getMophClientKey') ? getMophClientKey() : (defined('MOPH_CLIENT_KEY') ? MOPH_CLIENT_KEY : '')),
                'secret-key: ' . (function_exists('getMophSecretKey') ? getMophSecretKey() : (defined('MOPH_SECRET_KEY') ? MOPH_SECRET_KEY : '')),
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log('MOPH Notify cURL error: ' . $curlErr);
            return ['ok' => false, 'error' => $curlErr];
        }
        if ($httpCode >= 400) {
            error_log("MOPH Notify HTTP $httpCode: $response");
            return ['ok' => false, 'error' => "HTTP $httpCode: $response"];
        }
        return ['ok' => true, 'response' => $response];
    } catch (Exception $e) {
        error_log('MOPH Notify exception: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
