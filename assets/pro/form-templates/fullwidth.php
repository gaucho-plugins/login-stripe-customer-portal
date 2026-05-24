<form method="post" action="" class="lscp-portal-form lscp-form--fullwidth" novalidate style="width:100%;margin:0 auto;padding:48px 32px;background:#fafafa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;box-sizing:border-box;">
{{nonce_field}}
<div style="max-width:520px;margin:0 auto;text-align:center;">
<h2 style="margin:0 0 12px;font-size:28px;font-weight:700;color:#1a1a1a;">{{heading}}</h2>
<p style="margin:0 0 32px;font-size:16px;color:#555;">{{subheading}}</p>
<label for="{{form_id}}" style="display:block;margin-bottom:8px;font-size:14px;font-weight:500;color:#333;text-align:left;">Email address</label>
<input type="email" name="email" id="{{form_id}}" required autocomplete="email" placeholder="{{placeholder}}" value="{{default_email}}" style="width:100%;padding:14px 16px;border:2px solid #ddd;border-radius:6px;font-size:16px;margin-bottom:20px;box-sizing:border-box;outline:none;" />
<input type="submit" value="{{button_text}}" style="background:{{primary_color}};color:#fff;border:none;padding:16px 32px;border-radius:6px;font-size:16px;font-weight:600;cursor:pointer;width:100%;" />
</div>
</form>
