<form method="post" action="" class="lscp-portal-form lscp-form--minimal" novalidate style="max-width:400px;width:100%;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
{{nonce_field}}
<h2 style="margin:0 0 8px;font-size:20px;font-weight:600;color:#1a1a1a;">{{heading}}</h2>
<p style="margin:0 0 16px;font-size:14px;color:#666;">{{subheading}}</p>
<label for="{{form_id}}" style="display:block;margin-bottom:6px;font-size:13px;font-weight:500;">Email address</label>
<input type="email" name="email" id="{{form_id}}" required autocomplete="email" placeholder="{{placeholder}}" value="{{default_email}}" style="width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:4px;font-size:14px;margin-bottom:16px;box-sizing:border-box;" />
<input type="submit" value="{{button_text}}" style="background:{{primary_color}};color:#fff;border:none;padding:11px 22px;border-radius:4px;font-size:14px;font-weight:600;cursor:pointer;width:100%;" />
</form>
