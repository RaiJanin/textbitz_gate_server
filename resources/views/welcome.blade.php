<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Textbitz Gate</title>
</head>
<body class="tbg">
    <!-- TextBitz Gate: how it works. Paste into a WordPress "Custom HTML" block. No JavaScript; all styles are scoped under .tbg -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap">
    <style>
    .tbg{--pri:#2563eb;--pri-soft:#dbeafe;--pri-text:#1e40af;--acc:#f59e0b;--acc-soft:#fef3c7;--acc-text:#92400e;--ink:#0a0a0f;--mute:#5b6270;--line:rgba(10,10,15,.1);--bg2:#f9fafb;--card:#fff;--bezel:#0b0b10;--a-bg:#f3f4f6;--a-hdr:linear-gradient(to top right,rgba(37,99,235,.9) 50%,rgba(217,119,6,.2));--a-chip:rgba(255,255,255,.2);--a-lbl:#6b7280;--a-item:rgba(255,255,255,.4);--a-bd:#e5e7eb;--a-t1:#1f2937;--a-t2:#4b5563;--a-nav:rgba(255,255,255,.9);--a-navbd:#e5e7eb;--a-on:#2563eb;--a-off:#6b7280;--pshadow:rgba(10,10,15,.22);--pring:transparent;
    font:16px/1.6 Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink);max-width:1120px;margin:0 auto;padding:0 24px;-webkit-font-smoothing:antialiased}
    .tbg *,.tbg *::before,.tbg *::after{box-sizing:border-box}
    .tbg h1,.tbg h2,.tbg h3,.tbg p,.tbg ul{margin:0;padding:0;font-family:inherit}
    .tbg ul{list-style:none}
    .tbg h1{font-size:clamp(34px,5vw,54px);line-height:1.06;letter-spacing:-.035em;font-weight:800;max-width:18ch;color:var(--ink)}
    .tbg h2{font-size:clamp(26px,3.2vw,36px);line-height:1.15;letter-spacing:-.03em;font-weight:800;color:var(--ink)}
    .tbg .hero{padding:56px 0 40px}
    .tbg .lead{font-size:19px;color:var(--mute);max-width:54ch;margin-top:18px}
    .tbg .live{display:inline-flex;align-items:center;gap:8px;margin-top:22px;padding:6px 14px;border-radius:999px;background:var(--pri-soft);color:var(--pri-text);font-size:14px;font-weight:600}
    .tbg .live i{width:8px;height:8px;border-radius:50%;background:var(--pri)}
    .tbg .stage{display:grid;grid-template-columns:1fr 340px;gap:64px;align-items:center;padding:40px 0 64px}
    .tbg .copy h2{margin-bottom:16px;max-width:20ch}
    .tbg .copy li{padding:14px 0;border-top:1px solid var(--line);color:var(--mute);font-size:17px}
    .tbg .copy li b{display:block;color:var(--ink);font-weight:600;margin-bottom:2px}
    .tbg .copy li:last-child{border-bottom:1px solid var(--line)}
    /* phone */
    .tbg .phone{width:320px;height:600px;margin:0 auto;padding:10px;border-radius:44px;background:var(--bezel);box-shadow:0 30px 60px var(--pshadow),0 0 0 1px var(--pring)}
    /* phone screen: recreated from the app's Home page (Figtree, Tailwind blue-600 / amber-500, gray-100 / gray-900) */
    .tbg .phone,.tbg .scr,.tbg .hdr,.tbg .item,.tbg .nav{transition:background-color .25s,color .25s,border-color .25s}
    .tbg .scr{position:relative;display:flex;flex-direction:column;height:100%;border-radius:34px;background:var(--a-bg);overflow:hidden;font-family:Figtree,Inter,system-ui,sans-serif}
    .tbg .scr::before{content:"";position:absolute;z-index:3;top:10px;left:50%;width:84px;height:22px;margin-left:-42px;border-radius:12px;background:var(--bezel)}
    .tbg .hdr{display:flex;align-items:center;gap:8px;padding:40px 16px 14px;background:var(--a-hdr),var(--a-bg)}
    .tbg .hdr i{display:grid;place-items:center;width:36px;height:36px;border-radius:12px;background:var(--a-chip);color:#f3f4f6}
    .tbg .hdr svg{width:18px;height:18px}
    .tbg .hdr b{display:block;font-size:20px;font-weight:700;line-height:1.2;color:#f3f4f6}
    .tbg .hdr small{display:block;font-size:12px;color:#e5e7eb}
    .tbg .body{flex:1;display:flex;flex-direction:column;gap:14px;padding:14px 14px 0;overflow:hidden}
    .tbg .banner{display:flex;justify-content:space-between;padding:16px;border-radius:16px;background:linear-gradient(to bottom right,#2563eb,#3b82f6 50%,rgba(245,158,11,.4))}
    .tbg .banner h3{font-size:26px;font-weight:700;line-height:1.1;color:#fff}
    .tbg .pill{display:inline-flex;align-items:center;gap:6px;margin:8px 0 10px;padding:3px 10px;border-radius:999px;background:rgba(255,255,255,.2);font-size:12px;color:#fff}
    .tbg .pill svg{width:14px;height:14px}
    .tbg .banner p{font-size:17px;font-weight:800;line-height:1.2;color:#fff}
    .tbg .banner small{display:block;margin-top:2px;font-size:12px;color:rgba(255,255,255,.7)}
    .tbg .go{display:inline-block;margin-top:10px;padding:6px 12px;border-radius:12px;background:rgba(255,255,255,.8);color:#2563eb;font-size:13px;box-shadow:0 1px 3px rgba(0,0,0,.2)}
    .tbg .banner > svg{flex:none;align-self:flex-end;width:76px;height:76px;color:rgba(255,255,255,.9)}
    .tbg .sec{display:flex;justify-content:space-between;align-items:center}
    .tbg .sec span{font-size:13px;font-weight:600;text-transform:uppercase;color:var(--a-lbl)}
    .tbg .sec em{font-style:normal;padding:3px 10px;border-radius:999px;background:#3b82f6;color:#fff;font-size:11px;box-shadow:0 1px 2px rgba(0,0,0,.2)}
    .tbg .items{display:flex;flex-direction:column;gap:8px}
    .tbg .item{display:flex;align-items:center;gap:6px;padding:8px 12px 8px 8px;border-radius:16px;border:1px solid var(--a-bd);background:var(--a-item);box-shadow:0 1px 3px rgba(0,0,0,.12)}
    .tbg .item svg{flex:none;width:26px;height:26px}
    .tbg .item.in svg{color:#3b82f6}.tbg .item.out svg{color:#f59e0b}
    .tbg .item p{font-size:13px;line-height:1.3;color:var(--a-t1)}
    .tbg .item small{font-size:12px;color:var(--a-t2)}
    .tbg .nav{display:flex;justify-content:space-between;padding:0 16px;border-top:1px solid var(--a-navbd);background:var(--a-nav);backdrop-filter:blur(16px)}
    .tbg .nav span{display:flex;padding:12px 8px 18px;color:var(--a-off)}
    .tbg .nav span.on{color:var(--a-on)}
    .tbg .nav svg{width:24px;height:24px}
    /* flow */
    .tbg .flow{padding:64px 0;border-top:1px solid var(--line)}
    .tbg .flow .sub{color:var(--mute);font-size:18px;margin:12px 0 36px;max-width:56ch}
    .tbg .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;counter-reset:s}
    .tbg .step{position:relative;padding:26px;border-radius:14px;background:var(--card);box-shadow:0 0 0 1px var(--line)}
    .tbg .step::before{counter-increment:s;content:counter(s);display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:var(--acc);color:#1c1200;font-weight:700;font-size:14px;margin-bottom:14px}
    .tbg .step h3{font-size:18px;letter-spacing:-.01em;margin-bottom:6px;color:var(--ink)}
    .tbg .step p{color:var(--mute);font-size:15px}
    .tbg .note{margin-top:28px;padding:18px 22px;border-radius:12px;background:var(--bg2);box-shadow:0 0 0 1px var(--line);color:var(--mute);font-size:15px}
    .tbg .note b{color:var(--ink)}
    .tbg .top{display:flex;align-items:center;justify-content:space-between;height:72px;border-bottom:1px solid var(--line)}
    .tbg .logo{display:flex;align-items:center;gap:10px;font-weight:700;font-size:17px;letter-spacing:-.01em;color:var(--ink);text-decoration:none}
    .tbg .logo i{width:30px;height:30px;border-radius:8px;background:var(--pri);display:grid;place-items:center}
    .tbg .logo svg{width:16px;height:16px}
    .tbg .admin{display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 18px;border-radius:10px;background:var(--pri);color:#fff;font-weight:600;font-size:14px;text-decoration:none}
    .tbg .admin:hover{filter:brightness(.94);color:#fff}
    .tbg .admin:focus-visible,.tbg .logo:focus-visible{outline:2px solid var(--acc);outline-offset:3px}
    .tbg .admin svg{width:15px;height:15px}
    .tbg .dk{position:absolute;opacity:0;pointer-events:none}
    .tbg .acts{display:flex;align-items:center;gap:12px}
    .tbg .mode{display:grid;place-items:center;width:40px;height:40px;border-radius:10px;cursor:pointer;color:var(--ink);box-shadow:0 0 0 1px var(--line)}
    .tbg .mode svg{width:18px;height:18px}
    .tbg .mode .sun{display:none}
    .tbg .dk:focus-visible ~ .top .mode{outline:2px solid var(--acc);outline-offset:2px}
    .tbg:has(#tbg-dark:checked){--ink:#f4f4f6;--mute:#a1a1ad;--line:rgba(255,255,255,.12);--bg2:#14141c;--card:#1a1a24;--bezel:#2b2b38;--a-bg:#111827;--a-hdr:linear-gradient(to top right,rgba(31,41,55,.9) 50%,rgba(59,130,246,.2));--a-chip:rgba(59,130,246,.8);--a-lbl:#9ca3af;--a-item:rgba(255,255,255,.1);--a-bd:#374151;--a-t1:#e5e7eb;--a-t2:#9ca3af;--a-nav:rgba(31,41,55,.9);--a-navbd:#4b5563;--a-on:#3b82f6;--a-off:#d1d5db;--pshadow:rgba(0,0,0,.55);--pring:rgba(255,255,255,.14);--pri-soft:#172554;--pri-text:#93c5fd;--acc-soft:#3a2a05;--acc-text:#fcd34d;background:#0b0b10;box-shadow:0 0 0 100vmax #0b0b10;clip-path:inset(0 -100vmax)}
    .tbg:has(#tbg-dark:checked) .mode .sun{display:block}
    .tbg:has(#tbg-dark:checked) .mode .moon{display:none}
    @media (max-width:860px){
    .tbg .stage{grid-template-columns:1fr;gap:40px}
    .tbg .steps{grid-template-columns:1fr}
    .tbg .hero{padding-top:32px}
    }
    </style>

    <div>
    <input class="dk" type="checkbox" id="tbg-dark" aria-label="Dark mode">

    <header class="top">
        <a class="logo" href="./">
        <i><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></i>
        TextBitz Gate
        </a>
        <div class="acts">
        <label class="mode" for="tbg-dark" title="Toggle dark mode">
            <svg class="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
            <svg class="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        </label>
        <!-- Change href to your real admin login URL -->
        <a class="admin" href="/admin/login">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        Admin sign in
        </a>
        </div>
    </header>

    <div class="hero">
        <h1>See how TextBitz Gate works</h1>
        <p class="lead">Every tap on the school turnstile becomes a notification on a parent's phone.</p>
        <span class="live"><i></i>Live at a partner school</span>
    </div>

    <div class="stage">
        <div>
        <div class="copy">
            <h2>Parents see every tap as it happens</h2>
            <ul>
            <li><b>A notification when a child enters or leaves</b>It appears on the phone within moments of the tap.</li>
            <li><b>All children in one account</b>Switch between them with a single tap.</li>
            <li><b>A timeline of the day</b>Review every arrival and departure in one list.</li>
            </ul>
        </div>
        </div>

        <div class="phone" role="img" aria-label="Mockup of the TextBitz Gate mobile app home screen">
        <div class="scr">
            <div class="hdr">
            <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg></i>
            <div><b>TextBitz Gate</b><small>Attendance alerts</small></div>
            </div>
            <div class="body">
            <div class="banner">
                <div>
                <h3>Hello Ana</h3>
                <span class="pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>Live updates</span>
                <p>Mia is on campus</p>
                <small>Entered at 7:42 AM</small>
                <span class="go">View today</span>
                </div>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
            </div>
            <div class="sec"><span>Recent activity</span><em>See all</em></div>
            <div class="items">
                <div class="item in"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5" fill="currentColor"/></svg><div><p>Mia entered campus</p><small>Turnstile 2 · 7:42 AM</small></div></div>
                <div class="item in"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5" fill="currentColor"/></svg><div><p>Leo entered campus</p><small>Turnstile 1 · 7:31 AM</small></div></div>
                <div class="item out"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5" fill="currentColor"/></svg><div><p>Mia left campus</p><small>Yesterday · 4:58 PM</small></div></div>
            </div>
            </div>
            <div class="nav">
            <span class="on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg></span>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.2a6.5 6.5 0 0 1 3.5 5.8"/></svg></span>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg></span>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg></span>
            </div>
        </div>
        </div>
    </div>

    <div class="flow">
        <h2>From turnstile to phone</h2>
        <p class="sub">The school keeps the hardware and the data. The app only shows what the school server sends.</p>
        <div class="steps">
        <div class="step"><h3>A student taps in</h3><p>The RFID card is read at the turnstile on the way in or out.</p></div>
        <div class="step"><h3>The school server records it</h3><p>Each school runs its own server, which receives every tap.</p></div>
        <div class="step"><h3>The app notifies the family</h3><p>The server pushes the update and the phone shows a notification.</p></div>
        </div>
        <p class="note"><b>No Firebase needed.</b> Alerts use on-device notifications. A recurring background check also catches any update a live connection missed.</p>
    </div>
    </div>

    <script>
    /* Remembers the dark mode choice; without this script the toggle still works but resets on reload */
    (function(){var c=document.getElementById('tbg-dark');if(!c)return;try{var v=localStorage.getItem('tbg-dark');c.checked=v?v==='1':matchMedia('(prefers-color-scheme: dark)').matches;c.addEventListener('change',function(){localStorage.setItem('tbg-dark',c.checked?'1':'0')})}catch(e){}})();
    </script>
</body>
</html>