<form method="post" action="" class="lscp-portal-form lscp-form--card" novalidate style="max-width:420px;width:100%;margin:0 auto;background:#fff;border-radius:8px;border-top:4px solid {{primary_color}};box-shadow:0 1px 3px rgba(0,0,0,0.08),0 1px 2px rgba(0,0,0,0.04);padding:32px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;box-sizing:border-box;">
{{nonce_field}}
<h2 style="margin:0 0 8px;font-size:22px;font-weight:600;color:{{primary_color}};">{{heading}}</h2>
<p style="margin:0 0 24px;font-size:14px;color:#555;">{{subheading}}</p>
<label for="{{form_id}}" style="display:block;margin-bottom:6px;font-size:13px;font-weight:500;color:#333;">Email address</label>
<input type="email" name="email" id="{{form_id}}" required autocomplete="email" placeholder="{{placeholder}}" value="{{default_email}}" style="width:100%;padding:12px 14px;border:1px solid #d0d0d0;border-radius:6px;font-size:15px;margin-bottom:20px;box-sizing:border-box;outline:none;" />
<input type="submit" value="{{button_text}}" style="background:{{primary_color}};color:#fff;border:none;padding:13px 28px;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;width:100%;" />
</form>
