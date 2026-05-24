<form method="post" action="" class="lscp-portal-form lscp-form--inline" novalidate style="max-width:600px;width:100%;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
{{nonce_field}}
<div style="text-align:center;margin-bottom:16px;">
<h2 style="margin:0 0 6px;font-size:20px;font-weight:600;color:#1a1a1a;">{{heading}}</h2>
<p style="margin:0;font-size:14px;color:#666;">{{subheading}}</p>
</div>
<div style="display:flex;gap:8px;align-items:stretch;flex-wrap:wrap;">
<label for="{{form_id}}" style="position:absolute;left:-9999px;">Email address</label>
<input type="email" name="email" id="{{form_id}}" required autocomplete="email" placeholder="{{placeholder}}" value="{{default_email}}" style="flex:1 1 240px;min-width:0;padding:11px 14px;border:1px solid #ccc;border-radius:4px;font-size:14px;box-sizing:border-box;" />
<input type="submit" value="{{button_text}}" style="background:{{primary_color}};color:#fff;border:none;padding:11px 22px;border-radius:4px;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap;" />
</div>
</form>
