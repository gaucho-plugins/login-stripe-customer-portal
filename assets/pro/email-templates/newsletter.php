<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{heading}}</title>
</head>
<body style="margin:0;padding:0;background:#eef0f2;font-family:Georgia,'Times New Roman',serif;color:#222222;line-height:1.6;">
<div style="max-width:600px;margin:0 auto;background:#ffffff;">
<div style="padding:24px 32px;border-bottom:3px solid {{primary_color}};">
<h1 style="margin:0;font-size:28px;font-weight:400;color:{{primary_color}};font-style:italic;">{{site_name}}</h1>
</div>
<div style="padding:32px;">
<h2 style="margin:0 0 16px;font-size:22px;font-weight:400;">{{heading}}</h2>
<p style="margin:0 0 24px;font-size:16px;">Hello,</p>
<p style="margin:0 0 24px;font-size:16px;">You requested a magic-link login. To complete sign-in, click the button below.</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 32px;">
<tr><td style="background:{{primary_color}};border-radius:2px;">
<a href="{{magic_link}}" style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-family:-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;font-weight:600;font-size:15px;">{{cta_text}}</a>
</td></tr>
</table>
<p style="margin:0 0 8px;font-size:14px;color:#666666;">Trouble with the button? Use this link:</p>
<p style="margin:0 0 32px;font-size:14px;word-break:break-all;"><a href="{{magic_link}}" style="color:{{primary_color}};">{{magic_link}}</a></p>
</div>
<div style="padding:24px 32px;background:#fafafa;border-top:1px solid #e0e0e0;">
<p style="margin:0;font-size:13px;color:#777777;font-family:-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;">{{footer_text}}</p>
</div>
</div>
</body>
</html>
