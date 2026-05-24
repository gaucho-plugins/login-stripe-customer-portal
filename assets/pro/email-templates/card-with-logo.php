<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{heading}}</title>
</head>
<body style="margin:0;padding:24px;background:#f6f7f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1a1a1a;line-height:1.5;">
<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.04);overflow:hidden;">
<div style="padding:32px;text-align:center;border-bottom:1px solid #eeeeee;">
<img src="{{logo_url}}" alt="{{site_name}}" style="max-height:48px;max-width:240px;height:auto;width:auto;display:inline-block;">
</div>
<div style="padding:32px;">
<h1 style="margin:0 0 16px;font-size:22px;font-weight:600;color:{{primary_color}};text-align:center;">{{heading}}</h1>
<p style="margin:0 0 24px;font-size:15px;text-align:center;">A login link was requested for <strong>{{customer_email}}</strong>.</p>
<p style="margin:0 0 24px;text-align:center;">
<a href="{{magic_link}}" style="display:inline-block;background:{{primary_color}};color:#ffffff;padding:14px 32px;text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">{{cta_text}}</a>
</p>
<p style="margin:0 0 4px;font-size:12px;color:#888888;text-align:center;">Or copy this link:</p>
<p style="margin:0 0 24px;font-size:12px;word-break:break-all;color:#666666;text-align:center;">{{magic_link}}</p>
</div>
<div style="padding:16px 32px;background:#fafbfc;border-top:1px solid #eeeeee;">
<p style="margin:0;font-size:12px;color:#888888;text-align:center;">{{footer_text}}</p>
</div>
</div>
</body>
</html>
