<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{heading}}</title>
</head>
<body style="margin:0;padding:0;background:#f6f9fc;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#32325d;line-height:1.5;">
<div style="max-width:480px;margin:0 auto;padding:40px 20px;">
<div style="background:#ffffff;border-radius:6px;padding:32px;box-shadow:0 7px 14px 0 rgba(60,66,87,0.08),0 3px 6px 0 rgba(0,0,0,0.05);">
<p style="margin:0 0 8px;font-size:13px;color:#8898aa;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">{{site_name}}</p>
<h1 style="margin:0 0 16px;font-size:20px;font-weight:600;color:#32325d;">{{heading}}</h1>
<p style="margin:0 0 24px;font-size:15px;color:#525f7f;">A login was requested for <strong>{{customer_email}}</strong>. Click below to continue. This link expires in one hour.</p>
<p style="margin:0 0 24px;">
<a href="{{magic_link}}" style="display:inline-block;background:{{primary_color}};color:#ffffff;padding:12px 24px;text-decoration:none;border-radius:4px;font-weight:500;font-size:14px;">{{cta_text}}</a>
</p>
<hr style="border:none;border-top:1px solid #e6ebf1;margin:24px 0;">
<p style="margin:0 0 4px;font-size:12px;color:#8898aa;">Or paste this URL into your browser:</p>
<p style="margin:0;font-size:12px;word-break:break-all;color:#525f7f;">{{magic_link}}</p>
</div>
<p style="margin:24px 0 0;font-size:11px;color:#8898aa;text-align:center;">{{footer_text}}</p>
</div>
</body>
</html>
