<form method="post" action="" class="lscp-portal-form lscp-form--centered" novalidate style="max-width:380px;width:100%;margin:0 auto;text-align:center;padding:24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;box-sizing:border-box;">
{{nonce_field}}
<h2 style="margin:0 0 12px;font-size:24px;font-weight:700;color:#1a1a1a;letter-spacing:-0.3px;">{{heading}}</h2>
<p style="margin:0 0 28px;font-size:15px;color:#666;line-height:1.5;">{{subheading}}</p>
<label for="{{form_id}}" style="position:absolute;left:-9999px;">Email address</label>
<input type="email" name="email" id="{{form_id}}" required autocomplete="email" placeholder="{{placeholder}}" value="{{default_email}}" style="width:100%;padding:12px 14px;border:1px solid #ccc;border-radius:6px;font-size:15px;margin-bottom:16px;box-sizing:border-box;text-align:center;outline:none;" />
<input type="submit" value="{{button_text}}" style="background:{{primary_color}};color:#fff;border:none;padding:13px 28px;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;width:100%;letter-spacing:0.3px;" />
</form>
