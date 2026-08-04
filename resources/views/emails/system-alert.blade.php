<!doctype html>
<html lang="en">
<body style="margin:0;background:#f3f8fa;font-family:Arial,sans-serif;color:#18283b">
<div style="max-width:620px;margin:30px auto;background:#fff;border:1px solid #dfe9ed;border-radius:14px;overflow:hidden">
    <div style="padding:22px 26px;background:linear-gradient(120deg,#e8fbfa,#eef1ff)">
        <div style="font-size:12px;font-weight:700;color:#078f8d;text-transform:uppercase;letter-spacing:1px">Agile Tech Solutions IIM</div>
        <h1 style="margin:8px 0 0;font-size:23px">{{ $alertTitle }}</h1>
    </div>
    <div style="padding:26px">
        <div style="display:inline-block;padding:5px 9px;border-radius:20px;background:#eef1ff;color:#3152ac;font-size:11px;font-weight:700">{{ $module }} · {{ ucfirst($severity) }}</div>
        <p style="margin:18px 0 0;font-size:14px;line-height:1.65;color:#526274">{{ $alertMessage }}</p>
    </div>
    <div style="padding:14px 26px;border-top:1px solid #e5edf0;color:#84919d;font-size:11px">This alert was generated automatically by Agile Tech Solutions IIM.</div>
</div>
</body>
</html>
