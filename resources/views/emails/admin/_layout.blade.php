@php
    /**
     * Minimal ticket notification layout (inline HTML, table-based for clients).
     *
     * @var string $headline
     * @var string $greetingName
     * @var string|null $htmlTitle Document &lt;title&gt; (e.g. match email subject when headline is hidden)
     * @var string|null $introHtml
     * @var string|null $bodyHtml
     * @var array<int, array{key?: string, label:string, value?:string|null, valueHtml?:string}> $rows
     *                                         Rows respect {@see AdminEmailTemplate::$detail_row_keys} order when set (semantic keys in optional key).
     * @var \App\Modules\Tickets\Models\Ticket|null $ticket
     * @var string|null $ctaUrl
     * @var string|null $ctaLabel
     * @var int|null $companyId
     * @var string|null $copyrightLine Single-line copyright (plain text; only {{year}} supported in DB template)
     * @var string|null $otpCode Centered OTP block (admin auth emails)
     * @var string $ctaAlign left|center ΓÇö CTA row alignment (auth reset/setup uses center)
     */
    $headline = $headline ?? 'Ticket update';
    $greetingName = $greetingName ?? 'Hi there';
    $htmlTitle = $htmlTitle ?? null;
    $rows = $rows ?? [];
    $ctaLabel = $ctaLabel ?? 'View Ticket';
    $companyId = $companyId ?? optional($ticket ?? null)->central_company_id;
    $copyrightLine = $copyrightLine ?? $footerHtml ?? null;
    $otpCode = $otpCode ?? null;
    $ctaAlign = $ctaAlign ?? 'left';
    $brandPrimary = config('brand.primary', '#14377d');
    $ctaButtonStyle = 'display:inline-block; padding:10px 20px; font-size:14px; font-weight:600; color:#ffffff; background-color:'.$brandPrimary.'; border-radius:6px; text-decoration:none;';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $htmlTitle ?? (filled($headline) ? $headline : (optional($ticket)->subject ?: 'Ticket update')) }} - AdaptiveQMS</title>
</head>
<body style="margin:0; padding:24px 12px; background-color:#f4f4f5; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        <tr>
            <td align="center" style="padding:0;">
                <table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0"
                    style="width:100%; max-width:600px; border-collapse:collapse; background-color:#ffffff; border:1px solid #e4e4e7; border-radius:8px;">
                    <tr>
                        <td style="padding:32px 36px; color:#18181b;">
                            @if (filled($headline))
                                <p style="margin:0 0 4px 0; font-size:13px; font-weight:600; color:#52525b;">
                                    {{ $headline }}
                                </p>
                            @endif
                            @if (filled($greetingName))
                                <h1 style="margin:0 0 20px 0; font-size:20px; line-height:1.35; font-weight:600; color:#09090b;">
                                    Hi {{ e($greetingName) }},
                                </h1>
                            @endif

                            @if (! empty($introHtml))
                                <div style="font-size:15px; line-height:1.6; color:#3f3f46;">
                                    {!! $introHtml !!}
                                </div>
                            @endif

                            @if (! empty($rows))
                                <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0"
                                    style="margin-top:24px; border-collapse:collapse; border-top:1px solid #e4e4e7;">
                                    @foreach ($rows as $row)
                                        @php
                                            $label = $row['label'] ?? '';
                                            $value = $row['value'] ?? null;
                                            $valueHtml = $row['valueHtml'] ?? null;
                                            $rendered = $valueHtml ?? ($value !== null && $value !== '' ? e($value) : ' - ');
                                            $detailKey = $row['key'] ?? '';
                                        @endphp
                                        <tr @if ($detailKey !== '') data-detail-key="{{ e($detailKey) }}" @endif>
                                            <td style="padding:10px 0; font-size:13px; color:#71717a; width:132px; vertical-align:top;">
                                                {{ $label }}
                                            </td>
                                            <td style="padding:10px 0; font-size:15px; color:#18181b; line-height:1.5; vertical-align:top;">
                                                {!! $rendered !!}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if (! empty($bodyHtml))
                                <div style="margin-top:24px; font-size:15px; line-height:1.6; color:#3f3f46;">
                                    {!! $bodyHtml !!}
                                </div>
                            @endif

                            @if (filled($otpCode))
                                <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                                    <tr>
                                        <td align="center" style="padding:8px 0;">
                                            <span style="display:inline-block; font-size:32px; font-weight:700; letter-spacing:8px; padding:16px 24px; background-color:#f4f4f5; border-radius:8px; color:#18181b;">
                                                {{ e($otpCode) }}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if (! empty($ctaUrl))
                                <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top:28px;">
                                    <tr>
                                        <td align="{{ $ctaAlign === 'center' ? 'center' : 'left' }}">
                                            <a href="{{ $ctaUrl }}" style="{{ $ctaButtonStyle }}">
                                                {{ e($ctaLabel) }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; border-collapse:collapse;">
                    <tr>
                        <td align="center" style="padding:12px 16px 0;">
                            @include('emails.admin.partials.powered-by-attribution', ['companyId' => $companyId])
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:4px 16px 24px; font-size:11px; color:#a1a1aa; line-height:16px;">
                            {{ $copyrightLine }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
