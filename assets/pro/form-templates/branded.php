<form method="post" action="" class="lscp-portal-form lscp-form--branded" novalidate style="max-width:440px;width:100%;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.08);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;box-sizing:border-box;">
{{nonce_field}}
<div style="background:{{primary_color}};padding:32px 32px 24px;text-align:center;">
<h2 style="margin:0;font-size:24px;font-weight:700;color:#fff;letter-spacing:-0.3px;">{{heading}}</h2>
<p style="margin:8px 0 0;font-size:14px;color:rgba(255,255,255,0.9);">{{subheading}}</p>
</div>
<div style="padding:24px 32px 28px;">
<label for="{{form_id}}" style="display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:#333;text-transform:uppercase;letter-spacing:0.4px;">Email address</label>
<input type="email" name="email" id="{{form_id}}" required autocomplete="email" placeholder="{{placeholder}}" value="{{default_email}}" style="width:100%;padding:12px 14px;border:2px solid #e0e0e0;border-radius:6px;font-size:15px;margin-bottom:20px;box-sizing:border-box;outline:none;" />
<input type="submit" value="{{button_text}}" style="background:{{primary_color}};color:#fff;border:none;padding:14px 28px;border-radius:6px;font-size:15px;font-weight:700;cursor:pointer;width:100%;text-transform:uppercase;letter-spacing:0.5px;" />
</div>
</form>
