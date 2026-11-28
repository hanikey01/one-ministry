@php
    $logoUrl = $settings->logo_path ? asset('storage/'.$settings->logo_path) : null;
    $supportEmail = $settings->contact_email;
@endphp
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting"><title>{{ $subjectLine }}</title></head>
<body style="margin:0;padding:0;background:{{ $palette['canvas'] }};color:{{ $palette['ink'] }};font-family:Arial,Helvetica,sans-serif">
<span style="display:none!important;max-height:0;overflow:hidden;opacity:0">{{ $subjectLine }}</span>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:{{ $palette['canvas'] }}"><tr><td align="center" style="padding:32px 14px">
<table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px">
<tr><td align="center" style="padding:0 6px 16px">@if($logoUrl)<img src="{{ $logoUrl }}" width="144" alt="{{ $settings->name }}" style="display:block;max-width:144px;max-height:42px;width:auto;height:auto;border:0">@else<strong style="color:{{ $palette['ink'] }};font-family:Georgia,'Times New Roman',serif;font-size:21px">{{ $settings->name }}</strong>@endif</td></tr>
<tr><td style="height:4px;background:{{ $palette['action'] }};border-radius:12px 12px 0 0;font-size:1px;line-height:1px">&nbsp;</td></tr>
<tr><td style="border:1px solid {{ $palette['line'] }};border-top:0;border-radius:0 0 12px 12px;padding:28px 24px;background:{{ $palette['surface'] }}">
<div style="margin-bottom:9px;color:{{ $palette['action'] }};font-size:10px;font-weight:800;letter-spacing:1.3px;text-transform:uppercase">{{ $settings->name }}</div>
<h1 style="margin:0 0 17px;color:{{ $palette['ink'] }};font-family:Georgia,'Times New Roman',serif;font-size:25px;line-height:1.25">{{ $heading }}</h1>
<div style="color:{{ $palette['muted'] }};font-size:14px;line-height:1.72">{!! nl2br(e($body)) !!}</div>
@if($actionText && $actionUrl)<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 4px"><tr><td bgcolor="{{ $palette['action'] }}" style="border-radius:8px"><a href="{{ $actionUrl }}" style="display:inline-block;padding:13px 19px;color:{{ $palette['action_text'] }}!important;-webkit-text-fill-color:{{ $palette['action_text'] }};font-size:14px;font-weight:800;text-decoration:none"><span style="color:{{ $palette['action_text'] }}!important;-webkit-text-fill-color:{{ $palette['action_text'] }}">{{ $actionText }}</span></a></td></tr></table>@endif
</td></tr>
<tr><td style="padding:18px 10px 0;color:{{ $palette['muted'] }};font-size:11px;line-height:1.6"><div style="border-top:1px solid {{ $palette['line'] }};padding-top:14px">This is a service email from {{ $settings->name }}.@if($supportEmail) For help, contact <a href="mailto:{{ $supportEmail }}" style="color:{{ $palette['ink'] }}">{{ $supportEmail }}</a>.@endif</div><div style="padding-top:5px">© {{ now()->year }} {{ $settings->name }}</div></td></tr>
</table></td></tr></table></body></html>
