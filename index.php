<?php
// index.php - AcadNexus Landing Page
session_start();

$ticker = [
    'Assignments', 'Attendance', 'Course materials', 'Announcements',
    'Faculty appointments', 'Academic progress', 'Project mentorship',
    'Exams and results', 'Digital student pass'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AcadNexus – Unified Student–Faculty Academic Hub</title>
    <meta name="description" content="AcadNexus brings students and faculty together in one academic workspace for assignments, attendance, course materials, announcements, appointments and results.">
    <meta name="theme-color" content="#0b2623">
    <script>document.documentElement.classList.add('js');</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700;12..96,800&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">

    <style>
        :root {
            --ink: #0b2623;
            --ink-2: #123a35;
            --teal: #0f766e;
            --teal-2: #14a396;
            --teal-soft: #dcefe9;
            --amber: #f4b63f;
            --amber-soft: #fdf0cf;
            --coral: #e4572e;
            --coral-soft: #fde6df;
            --paper: #f3f6f5;
            --white: #ffffff;
            --line: #dbe5e2;
            --text: #14302c;
            --muted: #587069;
            --display: 'Bricolage Grotesque', 'Segoe UI', sans-serif;
            --body: 'DM Sans', -apple-system, 'Segoe UI', Roboto, sans-serif;
            --radius: 22px;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html {
            scroll-behavior: smooth;
            scroll-padding-top: 90px;
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
        }

        body {
            font-family: var(--body);
            background: var(--paper);
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
            position: relative;
            -webkit-font-smoothing: antialiased;
        }

        ::selection { background: var(--amber); color: var(--ink); }
        a { color: inherit; }
        :focus-visible { outline: 3px solid var(--amber); outline-offset: 3px; border-radius: 6px; }

        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 28px; }

        /* ---------- Scroll progress ---------- */
        .progress {
            position: fixed; top: 0; left: 0; height: 3px; width: 100%;
            background: linear-gradient(90deg, var(--teal-2), var(--amber));
            transform-origin: left; transform: scaleX(0);
            z-index: 1100;
        }

        /* ---------- Reveal on scroll ---------- */
        .js .reveal { opacity: 0; translate: 0 28px; }
        .js .reveal.in { opacity: 1; translate: 0 0; }
        .reveal { transition: opacity 0.7s ease var(--d, 0s), translate 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0s); }

        /* ---------- Buttons ---------- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 26px;
            border-radius: 14px;
            font-family: var(--body);
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            border: 2px solid transparent;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform 0.15s ease, background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn svg { transition: transform 0.2s ease; }
        .btn:hover svg { transform: translateX(3px); }
        .btn-primary { background: linear-gradient(135deg, var(--ink) 0%, var(--ink-2) 100%); color: #fff; box-shadow: 0 12px 26px rgba(11, 38, 35, 0.25); }
        .btn-primary:hover { box-shadow: 0 16px 32px rgba(11, 38, 35, 0.32); }
        .btn-primary::after {
            content: ''; position: absolute; top: 0; left: -80%; width: 50%; height: 100%;
            background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.22), transparent);
            transform: skewX(-20deg); transition: left 0.6s ease;
        }
        .btn-primary:hover::after { left: 130%; }
        .btn-ghost { color: var(--ink); border-color: var(--line); background: #fff; }
        .btn-ghost:hover { border-color: var(--teal); color: var(--teal); }
        .btn-amber { background: linear-gradient(135deg, #ffc552 0%, var(--amber) 100%); color: var(--ink); box-shadow: 0 12px 26px rgba(244, 182, 63, 0.3); }
        .btn-amber:hover { box-shadow: 0 16px 32px rgba(244, 182, 63, 0.42); }
        .btn-outline-light { background: rgba(255, 255, 255, 0.04); color: #fff; border-color: rgba(255, 255, 255, 0.35); }
        .btn-outline-light:hover { border-color: #fff; background: rgba(255, 255, 255, 0.1); }
        .btn-sm { padding: 10px 20px; font-size: 14px; border-radius: 12px; }

        /* ---------- Navbar ---------- */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            background: rgba(243, 246, 245, 0.8);
            backdrop-filter: blur(16px) saturate(1.4);
            -webkit-backdrop-filter: blur(16px) saturate(1.4);
            border-bottom: 1px solid transparent;
            transition: box-shadow 0.25s ease, border-color 0.25s ease, background 0.25s ease;
        }
        .navbar.scrolled { border-bottom-color: var(--line); box-shadow: 0 10px 30px -12px rgba(11, 38, 35, 0.18); background: rgba(243, 246, 245, 0.92); }
        .nav-inner { height: 72px; display: flex; align-items: center; justify-content: space-between; gap: 24px; }

        .brand { display: flex; align-items: center; gap: 11px; text-decoration: none; }
        .brand-mark {
            width: 40px; height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--ink) 0%, var(--teal) 100%);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 18px rgba(15, 118, 110, 0.35);
        }
        .brand-name { font-family: var(--display); font-size: 22px; font-weight: 700; color: var(--ink); letter-spacing: -0.4px; }

        .nav-menu { display: flex; align-items: center; gap: 30px; list-style: none; }
        .nav-link { text-decoration: none; font-size: 15px; font-weight: 500; color: var(--muted); position: relative; padding: 4px 0; transition: color 0.15s ease; }
        .nav-link::after { content: ''; position: absolute; left: 0; bottom: -2px; height: 2px; width: 100%; background: var(--amber); transform: scaleX(0); transform-origin: left; transition: transform 0.25s ease; border-radius: 2px; }
        .nav-link:hover { color: var(--ink); }
        .nav-link:hover::after { transform: scaleX(1); }
        .nav-actions { display: flex; align-items: center; gap: 10px; }

        .hamburger {
            display: none;
            width: 44px; height: 44px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            cursor: pointer;
            align-items: center; justify-content: center;
            color: var(--ink);
        }

        /* ---------- Hero ---------- */
        .hero {
            padding: 150px 0 140px 0;
            position: relative;
            background-color: var(--paper);
            background-image: linear-gradient(to bottom, rgba(15, 118, 110, 0.07) 1px, transparent 1px);
            background-size: 100% 36px;
        }
        .hero-bg { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
        .blob { position: absolute; border-radius: 50%; filter: blur(70px); opacity: 0.75; }
        .blob-1 { width: 520px; height: 520px; right: -120px; top: -80px; background: radial-gradient(circle, rgba(20, 163, 150, 0.45), transparent 65%); animation: drift 14s ease-in-out infinite alternate; }
        .blob-2 { width: 460px; height: 460px; left: -140px; bottom: -60px; background: radial-gradient(circle, rgba(244, 182, 63, 0.42), transparent 65%); animation: drift 17s ease-in-out infinite alternate-reverse; }
        .blob-3 { width: 300px; height: 300px; left: 42%; top: 20%; background: radial-gradient(circle, rgba(228, 87, 46, 0.14), transparent 65%); animation: drift 20s ease-in-out infinite alternate; }
        @keyframes drift { from { transform: translate(0, 0) scale(1); } to { transform: translate(40px, 30px) scale(1.12); } }

        .hero::after {
            content: '';
            position: absolute; left: 0; right: 0; bottom: 0; height: 140px;
            background: linear-gradient(to bottom, transparent, var(--paper));
            pointer-events: none;
        }
        .hero-grid { display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 64px; align-items: center; position: relative; z-index: 1; }

        .hero-kicker {
            display: inline-flex; align-items: center; gap: 10px;
            background: rgba(255, 255, 255, 0.85); border: 1px solid var(--line);
            padding: 7px 16px 7px 8px; border-radius: 999px;
            font-size: 14px; font-weight: 500; color: var(--muted);
            margin-bottom: 26px;
            box-shadow: 0 6px 18px -8px rgba(11, 38, 35, 0.2);
        }
        .hero-kicker b { background: var(--amber); color: var(--ink); font-weight: 700; padding: 3px 10px; border-radius: 999px; font-size: 13px; display: inline-flex; align-items: center; gap: 7px; }
        .live-dot { width: 8px; height: 8px; border-radius: 50%; background: #12a37f; position: relative; display: inline-block; }
        .live-dot::after { content: ''; position: absolute; inset: -4px; border-radius: 50%; border: 2px solid #12a37f; opacity: 0; animation: ping 2s ease-out infinite; }
        @keyframes ping { 0% { transform: scale(0.5); opacity: 0.8; } 100% { transform: scale(1.5); opacity: 0; } }

        .hero-title {
            font-family: var(--display);
            font-size: clamp(42px, 5.6vw, 72px);
            line-height: 1.02;
            font-weight: 800;
            letter-spacing: -2px;
            color: var(--ink);
            margin-bottom: 22px;
        }
        .hl {
            background: linear-gradient(transparent 60%, rgba(244, 182, 63, 0.6) 60% 92%, transparent 92%);
            background-size: 0% 100%;
            background-repeat: no-repeat;
            padding: 0 6px;
            margin: 0 -6px;
            -webkit-box-decoration-break: clone; box-decoration-break: clone;
            animation: hlIn 0.9s 0.5s cubic-bezier(0.2, 0.7, 0.2, 1) forwards;
        }
        @keyframes hlIn { to { background-size: 100% 100%; } }

        .hero-desc { font-size: 19px; color: var(--muted); max-width: 540px; margin-bottom: 34px; line-height: 1.65; }
        .hero-buttons { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 34px; }

        .hero-checks { display: flex; flex-wrap: wrap; gap: 10px 22px; list-style: none; font-size: 14.5px; color: var(--muted); font-weight: 500; }
        .hero-checks li { display: flex; align-items: center; gap: 8px; }
        .hero-checks svg { color: #fff; background: var(--teal); border-radius: 50%; padding: 3px; flex-shrink: 0; }

        /* Hero product preview */
        .mock { position: relative; padding: 20px 0 70px 0; max-width: 100%; overflow: hidden; }
        .mock::before {
            content: ''; position: absolute; inset: 0 0 40px 0; border-radius: 36px;
            background: linear-gradient(135deg, rgba(20, 163, 150, 0.25), rgba(244, 182, 63, 0.25));
            filter: blur(30px); z-index: 0;
        }
        .mock-window {
            position: relative; z-index: 1;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: 0 40px 70px -24px rgba(11, 38, 35, 0.35);
            overflow: hidden;
            transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
        }
        .mock-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 22px; border-bottom: 1px solid var(--line); background: linear-gradient(to bottom, #fbfdfc, #fff); }
        .mock-dots { display: flex; gap: 6px; }
        .mock-dots i { width: 10px; height: 10px; border-radius: 50%; background: #e3e9e7; display: block; }
        .mock-dots i:nth-child(1) { background: #f1a28d; }
        .mock-dots i:nth-child(2) { background: #f4d28a; }
        .mock-dots i:nth-child(3) { background: #9fd6c8; }
        .mock-bar strong { font-family: var(--display); font-size: 19px; font-weight: 700; color: var(--ink); margin-right: auto; margin-left: 6px; }
        .mock-bar span { font-size: 13px; color: var(--muted); }
        .mock-rows { padding: 8px 22px 16px 22px; }
        .mock-row { display: grid; grid-template-columns: 62px 1fr auto; gap: 14px; align-items: center; padding: 14px 0; border-bottom: 1px dashed var(--line); }
        .mock-row:last-child { border-bottom: none; }
        .mock-time { font-size: 13px; font-weight: 700; color: var(--muted); }
        .mock-class { display: flex; gap: 12px; align-items: center; }
        .mock-class i { width: 5px; height: 36px; border-radius: 4px; display: block; flex-shrink: 0; }
        .mock-class b { display: block; font-size: 15px; color: var(--ink); line-height: 1.25; }
        .mock-class small { font-size: 12.5px; color: var(--muted); }
        .pill { font-size: 12px; font-weight: 700; padding: 4px 11px; border-radius: 999px; white-space: nowrap; }
        .pill-teal { background: var(--teal-soft); color: #0a5b55; }
        .pill-amber { background: var(--amber-soft); color: #8a5d00; }
        .pill-coral { background: var(--coral-soft); color: #a63a18; }
        .pill-ink { background: var(--ink); color: #fff; }

        .float-a { animation: floatY 6s ease-in-out infinite; }
        .float-b { animation: floatY 7.5s ease-in-out -2s infinite; }
        .float-c { animation: floatY 5.5s ease-in-out -1s infinite; }
        @keyframes floatY { 0%, 100% { translate: 0 0; } 50% { translate: 0 -10px; } }

        .mock-att {
            position: absolute; left: -34px; bottom: 0; z-index: 2;
            background: linear-gradient(135deg, var(--ink), var(--ink-2)); color: #fff;
            border-radius: 20px; padding: 16px 20px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: 0 24px 44px -12px rgba(11, 38, 35, 0.55);
        }
        .ring-wrap { position: relative; width: 70px; height: 70px; }
        .ring-wrap svg { width: 100%; height: 100%; transform: rotate(-90deg); }
        .ring-wrap .track { stroke: rgba(255, 255, 255, 0.15); }
        .ring-wrap .bar { stroke: var(--amber); stroke-linecap: round; }
        .ring-num { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-family: var(--display); font-size: 18px; font-weight: 700; }
        .mock-att b { display: block; font-size: 15px; }
        .mock-att small { color: #a9c4be; font-size: 12.5px; }

        .mock-due {
            position: absolute; right: -18px; bottom: 22px; z-index: 2;
            background: #fff; border: 1px solid var(--line);
            border-radius: 18px; padding: 14px 18px; max-width: 250px;
            box-shadow: 0 24px 44px -14px rgba(11, 38, 35, 0.35);
        }
        .mock-due small { font-size: 12px; color: var(--coral); font-weight: 700; display: block; margin-bottom: 2px; }
        .mock-due b { font-size: 14.5px; color: var(--ink); line-height: 1.3; display: block; margin-bottom: 10px; }

        .mock-grade {
            position: absolute; right: 26px; top: -14px; z-index: 2;
            background: var(--amber); color: var(--ink);
            border-radius: 14px; padding: 9px 14px;
            display: flex; align-items: center; gap: 9px;
            font-size: 13px; font-weight: 700;
            box-shadow: 0 16px 30px -10px rgba(244, 182, 63, 0.7);
        }
        .mock-grade span { background: var(--ink); color: var(--amber); border-radius: 8px; padding: 2px 8px; font-family: var(--display); font-size: 15px; }
        .mock-caption { position: absolute; right: 4px; bottom: -4px; font-size: 11.5px; color: var(--muted); }

        .ring-anim { animation: ringIn 1.4s 0.3s cubic-bezier(0.2, 0.7, 0.2, 1) both; }
        @keyframes ringIn { from { stroke-dashoffset: 213.6; } }

        /* ---------- Perks band ---------- */
        .perks { margin-top: -70px; position: relative; z-index: 5; }
        .perks-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
        .perk {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 22px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: 0 20px 40px -22px rgba(11, 38, 35, 0.35);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }
        .perk:hover { transform: translateY(-5px); border-color: var(--teal); box-shadow: 0 26px 46px -20px rgba(15, 118, 110, 0.4); }
        .perk-ico {
            width: 52px; height: 52px; border-radius: 16px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--ink), var(--teal));
            color: var(--amber);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 20px -8px rgba(15, 118, 110, 0.6);
        }
        .perk b { display: block; font-family: var(--display); font-size: 17px; color: var(--ink); line-height: 1.2; margin-bottom: 2px; }
        .perk small { font-size: 13.5px; color: var(--muted); line-height: 1.4; display: block; }

        /* ---------- Marquee ---------- */
        .marquee { margin-top: 70px; background: var(--ink); padding: 18px 0; overflow: hidden; position: relative; border-top: 3px solid var(--amber); }
        .marquee::before, .marquee::after { content: ''; position: absolute; top: 0; bottom: 0; width: 90px; z-index: 1; pointer-events: none; }
        .marquee::before { left: 0; background: linear-gradient(to right, var(--ink), transparent); }
        .marquee::after { right: 0; background: linear-gradient(to left, var(--ink), transparent); }
        .marquee-track { display: flex; width: max-content; animation: scrollX 38s linear infinite; }
        .marquee:hover .marquee-track { animation-play-state: paused; }
        .marquee-item { display: flex; align-items: center; gap: 22px; padding-right: 22px; font-family: var(--display); font-size: 22px; font-weight: 600; color: #e3efec; white-space: nowrap; }
        .marquee-item svg { color: var(--amber); flex-shrink: 0; }
        @keyframes scrollX { to { transform: translateX(-50%); } }

        /* ---------- Section scaffolding ---------- */
        .section { padding: 100px 0; }
        .section-head { max-width: 720px; margin-bottom: 52px; }
        .section-pill { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: var(--teal-soft); color: #0a5b55; font-size: 13.5px; font-weight: 600; margin-bottom: 18px; }
        .section-pill svg { color: var(--teal); }
        .section-title { font-family: var(--display); font-size: clamp(32px, 4vw, 48px); line-height: 1.08; font-weight: 700; letter-spacing: -1.2px; color: var(--ink); margin-bottom: 14px; }
        .section-sub { font-size: 18px; color: var(--muted); max-width: 600px; }

        /* ---------- Bento features ---------- */
        .bento { display: grid; grid-template-columns: repeat(6, 1fr); gap: 20px; }
        .tile {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 28px;
            display: flex; flex-direction: column;
            position: relative; overflow: hidden;
            transition: opacity 0.7s ease var(--d, 0s), translate 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0s), transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
        }
        .tile::before {
            content: ''; position: absolute; inset: 0; pointer-events: none; opacity: 0;
            background: radial-gradient(420px circle at var(--mx, 50%) var(--my, 50%), rgba(20, 163, 150, 0.14), transparent 45%);
            transition: opacity 0.3s ease;
        }
        .tile:hover::before { opacity: 1; }
        .tile:hover { border-color: var(--teal); transform: translateY(-4px); box-shadow: 0 28px 50px -26px rgba(15, 118, 110, 0.45); }
        .tile > * { position: relative; z-index: 1; }
        .tile h3 { font-family: var(--display); font-size: 21px; font-weight: 700; color: var(--ink); letter-spacing: -0.3px; margin-bottom: 8px; }
        .tile p { font-size: 15px; color: var(--muted); line-height: 1.6; }
        .tile-visual { margin-top: auto; padding-top: 22px; }
        .tile-ico {
            width: 46px; height: 46px; border-radius: 14px; margin-bottom: 18px;
            background: var(--teal-soft); color: var(--teal);
            display: flex; align-items: center; justify-content: center;
        }

        .t-assign { grid-column: span 3; grid-row: span 2; }
        .t-attend { grid-column: span 3; flex-direction: row; align-items: center; gap: 24px; }
        .t-announce { grid-column: span 3; }
        .t-materials, .t-appoint, .t-progress { grid-column: span 2; }
        .t-projects, .t-results, .t-pass { grid-column: span 2; }

        .t-dark { background: linear-gradient(145deg, var(--ink) 0%, var(--ink-2) 100%); border-color: var(--ink); }
        .t-dark::before { background: radial-gradient(420px circle at var(--mx, 50%) var(--my, 50%), rgba(244, 182, 63, 0.16), transparent 45%); }
        .t-dark h3 { color: #fff; }
        .t-dark p { color: #a9c4be; }
        .t-dark:hover { border-color: var(--amber); }
        .t-dark .tile-ico { background: rgba(244, 182, 63, 0.15); color: var(--amber); }
        .t-tint { background: linear-gradient(145deg, var(--teal-soft), #eaf6f2); border-color: #bfe0d7; }
        .t-tint .tile-ico { background: #fff; }

        .list-ui { display: flex; flex-direction: column; gap: 10px; }
        .list-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 16px; background: var(--paper); border-radius: 14px; border: 1px solid transparent; transition: border-color 0.2s ease, background 0.2s ease; }
        .list-item:hover { background: #fff; border-color: var(--line); }
        .list-item b { font-size: 14.5px; color: var(--ink); display: block; line-height: 1.3; }
        .list-item small { font-size: 12.5px; color: var(--muted); }

        .chip-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .chip { font-size: 13px; font-weight: 600; padding: 7px 13px; border-radius: 10px; background: var(--paper); color: var(--ink); border: 1px solid var(--line); }
        .chip.on { background: var(--ink); color: #fff; border-color: var(--ink); box-shadow: 0 8px 16px -6px rgba(11, 38, 35, 0.5); }
        .chip.file { display: inline-flex; gap: 8px; align-items: center; }
        .chip.file em { font-style: normal; font-size: 11px; font-weight: 800; padding: 2px 6px; border-radius: 5px; color: #fff; }

        .bars { display: flex; align-items: flex-end; gap: 10px; height: 90px; }
        .bars span { flex: 1; background: linear-gradient(to top, var(--teal), var(--teal-2)); border-radius: 8px 8px 3px 3px; opacity: 0.35; display: block; transform-origin: bottom; }
        .bars span:last-child { opacity: 1; }
        .js .bars span { transform: scaleY(0); transition: transform 0.9s cubic-bezier(0.2, 0.7, 0.2, 1); }
        .js .reveal.in .bars span { transform: scaleY(1); }
        .js .reveal.in .bars span:nth-child(2) { transition-delay: 0.08s; }
        .js .reveal.in .bars span:nth-child(3) { transition-delay: 0.16s; }
        .js .reveal.in .bars span:nth-child(4) { transition-delay: 0.24s; }
        .js .reveal.in .bars span:nth-child(5) { transition-delay: 0.32s; }
        .bars-labels { display: flex; gap: 10px; margin-top: 8px; font-size: 12px; color: var(--muted); }
        .bars-labels span { flex: 1; text-align: center; }

        .rail { height: 8px; background: rgba(15, 118, 110, 0.18); border-radius: 99px; overflow: hidden; margin: 8px 0 14px 0; }
        .rail i { display: block; height: 100%; background: linear-gradient(90deg, var(--teal), var(--teal-2)); border-radius: 99px; }
        .js .rail i { width: 0 !important; transition: width 1.2s cubic-bezier(0.2, 0.7, 0.2, 1) 0.2s; }
        .js .reveal.in .rail i { width: var(--w) !important; }
        .mini-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .mini-table td { padding: 9px 0; border-bottom: 1px solid var(--line); color: var(--text); }
        .mini-table tr:last-child td { border-bottom: none; }
        .mini-table td:last-child { text-align: right; font-weight: 700; color: var(--teal); }

        .qr-mini { width: 76px; height: 76px; background: #fff; border-radius: 12px; padding: 7px; box-shadow: 0 0 0 4px rgba(244, 182, 63, 0.25); }

        /* ---------- Workflow ---------- */
        .how { background: #fff; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); position: relative; overflow: hidden; }
        .how::before { content: ''; position: absolute; right: -160px; top: -160px; width: 420px; height: 420px; border-radius: 50%; background: radial-gradient(circle, rgba(244, 182, 63, 0.2), transparent 65%); pointer-events: none; }
        .how-grid { display: grid; grid-template-columns: 0.8fr 1.2fr; gap: 70px; align-items: start; position: relative; }
        .steps { position: relative; display: flex; flex-direction: column; gap: 38px; padding-left: 4px; }
        .steps::before { content: ''; position: absolute; left: 25px; top: 20px; bottom: 20px; width: 3px; border-radius: 3px; background: linear-gradient(to bottom, var(--amber), var(--teal-2), var(--line)); }
        .step { display: grid; grid-template-columns: 52px 1fr; gap: 24px; position: relative; padding: 18px 22px 18px 0; border-radius: 18px; transition: background 0.25s ease; }
        .step:hover { background: var(--paper); }
        .step-num {
            width: 52px; height: 52px; border-radius: 50%;
            background: var(--ink); color: var(--amber);
            display: flex; align-items: center; justify-content: center;
            font-family: var(--display); font-size: 20px; font-weight: 700;
            border: 5px solid #fff; position: relative; z-index: 1;
            box-shadow: 0 0 0 5px rgba(244, 182, 63, 0.22);
        }
        .step h3 { font-family: var(--display); font-size: 23px; font-weight: 700; color: var(--ink); margin: 6px 0 6px 0; letter-spacing: -0.4px; }
        .step p { font-size: 16px; color: var(--muted); max-width: 520px; }

        /* ---------- Roles ---------- */
        .tabs { display: inline-flex; padding: 5px; background: #fff; border: 1px solid var(--line); border-radius: 16px; gap: 4px; margin-bottom: 28px; box-shadow: 0 10px 24px -14px rgba(11, 38, 35, 0.3); }
        .tab { font-family: var(--body); font-size: 15px; font-weight: 700; padding: 11px 26px; border-radius: 12px; border: none; background: transparent; color: var(--muted); cursor: pointer; transition: background 0.2s ease, color 0.2s ease; }
        .tab:hover { color: var(--ink); }
        .tab[aria-selected="true"] { background: var(--ink); color: #fff; }

        .role-panel {
            display: none; grid-template-columns: 1fr 1fr; gap: 0; color: #fff; border-radius: 30px; overflow: hidden;
            background:
                radial-gradient(620px circle at 100% 0%, rgba(20, 163, 150, 0.32), transparent 60%),
                radial-gradient(520px circle at 0% 100%, rgba(244, 182, 63, 0.16), transparent 60%),
                var(--ink);
            box-shadow: 0 40px 70px -34px rgba(11, 38, 35, 0.6);
        }
        .role-panel.active { display: grid; animation: panelIn 0.5s cubic-bezier(0.2, 0.7, 0.2, 1); }
        @keyframes panelIn { from { opacity: 0; transform: translateY(14px); } }
        .role-copy { padding: 52px; }
        .role-copy h3 { font-family: var(--display); font-size: 36px; line-height: 1.1; font-weight: 700; letter-spacing: -0.8px; margin-bottom: 14px; }
        .role-copy > p { color: #a9c4be; font-size: 17px; margin-bottom: 26px; max-width: 460px; }
        .role-list { list-style: none; display: grid; gap: 13px; margin-bottom: 34px; }
        .role-list li { display: flex; gap: 12px; align-items: flex-start; font-size: 16px; color: #e3efec; }
        .role-list svg { color: var(--ink); background: var(--amber); border-radius: 50%; padding: 3px; flex-shrink: 0; margin-top: 2px; }
        .role-actions { display: flex; flex-wrap: wrap; gap: 12px; }
        .role-visual { background: rgba(255, 255, 255, 0.03); padding: 44px; display: flex; flex-direction: column; justify-content: center; gap: 14px; border-left: 1px solid rgba(255, 255, 255, 0.08); }
        .rv-card { background: rgba(255, 255, 255, 0.07); backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 16px; padding: 16px 18px; display: flex; align-items: center; justify-content: space-between; gap: 14px; transition: transform 0.25s ease, background 0.25s ease; }
        .rv-card:hover { transform: translateX(6px); background: rgba(255, 255, 255, 0.12); }
        .rv-card b { display: block; font-size: 15px; color: #fff; }
        .rv-card small { color: #a9c4be; font-size: 13px; }

        /* ---------- CTA ---------- */
        .cta { padding: 20px 0 100px 0; }
        .cta-box {
            border-radius: 34px;
            padding: 76px 56px;
            position: relative; overflow: hidden;
            color: #fff;
            background:
                radial-gradient(rgba(255, 255, 255, 0.14) 1.5px, transparent 1.5px) 0 0 / 26px 26px,
                linear-gradient(135deg, var(--teal) 0%, #0b5f59 55%, var(--ink-2) 100%);
            display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 40px; align-items: center;
            box-shadow: 0 40px 70px -34px rgba(15, 118, 110, 0.7);
        }
        .cta-box::before { content: ''; position: absolute; right: -90px; top: -90px; width: 340px; height: 340px; border-radius: 50%; border: 50px solid rgba(255, 255, 255, 0.07); animation: spinSlow 40s linear infinite; }
        .cta-box::after { content: ''; position: absolute; left: -70px; bottom: -110px; width: 280px; height: 280px; border-radius: 50%; border: 36px solid rgba(244, 182, 63, 0.16); }
        @keyframes spinSlow { to { transform: rotate(360deg); } }
        .cta-box h2 { font-family: var(--display); font-size: clamp(32px, 4vw, 50px); line-height: 1.05; font-weight: 800; letter-spacing: -1.4px; margin-bottom: 14px; position: relative; z-index: 1; }
        .cta-box p { font-size: 18px; color: #d3efe9; max-width: 520px; position: relative; z-index: 1; }
        .cta-actions { display: flex; flex-direction: column; gap: 12px; position: relative; z-index: 1; }

        /* ---------- Footer ---------- */
        .footer { background: var(--ink); color: #a9c4be; padding: 70px 0 30px 0; position: relative; overflow: hidden; }
        .footer::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--teal-2), var(--amber), var(--coral)); }
        .footer-mark { position: absolute; left: 50%; bottom: -0.2em; transform: translateX(-50%); font-family: var(--display); font-weight: 800; font-size: clamp(90px, 18vw, 260px); letter-spacing: -6px; color: rgba(255, 255, 255, 0.035); white-space: nowrap; pointer-events: none; user-select: none; line-height: 1; }
        .footer .container { position: relative; z-index: 1; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 48px; }
        .footer-brand .brand-name { color: #fff; }
        .footer-brand p { margin-top: 16px; font-size: 15px; max-width: 320px; }
        .footer h4 { color: #fff; font-size: 15px; font-weight: 700; margin-bottom: 16px; }
        .footer ul { list-style: none; display: grid; gap: 11px; }
        .footer ul a { text-decoration: none; font-size: 14.5px; transition: color 0.15s ease, padding-left 0.2s ease; }
        .footer ul a:hover { color: var(--amber); padding-left: 4px; }
        .footer-bottom { border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 24px; font-size: 14px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; }

        /* ---------- Back to top ---------- */
        .to-top {
            position: fixed; right: 22px; bottom: 22px; z-index: 900;
            width: 48px; height: 48px; border-radius: 50%; border: none; cursor: pointer;
            background: var(--ink); color: var(--amber);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 14px 28px -8px rgba(11, 38, 35, 0.5);
            opacity: 0; pointer-events: none; transform: translateY(12px);
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        .to-top.show { opacity: 1; pointer-events: auto; transform: translateY(0); }
        .to-top:hover { background: var(--teal); }

        /* ---------- Responsive ---------- */
        @media (min-width: 1041px) {
            .mock-window { transform: perspective(1400px) rotateY(-6deg) rotateX(2deg); }
            .mock:hover .mock-window { transform: none; }
        }

        @media (max-width: 1040px) {
            .hero-grid { grid-template-columns: 1fr; gap: 48px; }
            .mock { max-width: 100%; margin: 0 auto; }
            .perks-grid { grid-template-columns: repeat(2, 1fr); }
            .bento { grid-template-columns: repeat(2, 1fr); }
            .t-assign, .t-attend, .t-announce, .t-materials, .t-appoint, .t-progress, .t-projects, .t-results, .t-pass { grid-column: span 1; grid-row: auto; }
            .t-assign { grid-column: span 2; }
            .t-attend { flex-direction: column; align-items: flex-start; }
            .how-grid { grid-template-columns: 1fr; gap: 40px; }
            .role-panel.active { grid-template-columns: 1fr; }
            .role-visual { border-left: none; border-top: 1px solid rgba(255, 255, 255, 0.08); }
            .cta-box { grid-template-columns: 1fr; padding: 48px 28px; }
            .cta-actions { flex-direction: row; flex-wrap: wrap; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 820px) {
            .hamburger {
                display: inline-flex;
                -webkit-tap-highlight-color: transparent;
            }
            .nav-actions .btn-login { display: none; }
            .nav-menu {
                display: none;
                position: fixed;
                top: 72px; left: 0; right: 0; bottom: 0;
                flex-direction: column; align-items: stretch; gap: 8px;
                padding: 24px 20px;
                background: rgba(243, 246, 245, 0.98);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-top: 1px solid var(--line);
                overflow-y: auto;
                z-index: 999;
                box-shadow: 0 30px 50px rgba(11, 38, 35, 0.15);
            }
            .nav-menu.open { display: flex; animation: navSlideDown 0.25s ease forwards; }
            @keyframes navSlideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
            .nav-menu li { width: 100%; list-style: none; }
            .nav-link {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 14px 18px;
                font-size: 16px;
                font-weight: 700;
                color: var(--ink);
                border-radius: 14px;
                background: #ffffff;
                border: 1px solid var(--line);
                text-decoration: none;
                transition: all 0.2s ease;
            }
            .nav-link:hover, .nav-link:active {
                background: var(--teal-soft);
                color: var(--teal);
                border-color: var(--teal-2);
            }
            .nav-link::after { display: none; }
        }

        @media (max-width: 640px) {
            .container { padding: 0 16px; }
            .section { padding: 56px 0; }
            .hero { padding: 95px 0 60px 0; }
            .hero-title {
                font-size: 32px;
                letter-spacing: -1px;
                line-height: 1.15;
            }
            .hero-desc {
                font-size: 15px;
                margin-bottom: 24px;
            }
            .hero-kicker {
                font-size: 12px;
                padding: 5px 12px 5px 6px;
                max-width: 100%;
                flex-wrap: wrap;
                margin-bottom: 18px;
            }
            .hero-buttons {
                flex-direction: column;
                width: 100%;
                gap: 10px;
                margin-bottom: 24px;
            }
            .hero-buttons .btn {
                width: 100%;
                padding: 13px 20px;
                font-size: 14.5px;
                justify-content: center;
            }
            .nav-actions .btn-sm {
                padding: 9px 14px;
                font-size: 13px;
                border-radius: 10px;
            }
            .hero-checks {
                flex-direction: column;
                gap: 10px;
                font-size: 13px;
            }
            .perks { margin-top: 20px; }
            .perks-grid { grid-template-columns: 1fr; gap: 14px; }
            .bento { grid-template-columns: 1fr; gap: 14px; }
            .t-assign { grid-column: span 1; }
            .t-appoint { flex-direction: column; align-items: flex-start; }
            .role-copy, .role-visual { padding: 24px 18px; }
            .mock { padding: 10px 0 0 0; }
            .mock-window { border-radius: 18px; }
            .mock-bar { padding: 12px 14px; }
            .mock-rows { padding: 4px 14px 12px 14px; }
            .mock-row { grid-template-columns: 50px 1fr auto; gap: 10px; padding: 10px 0; }
            .mock-class b { font-size: 13.5px; }
            .mock-att {
                position: static;
                margin-top: 14px;
                width: 100%;
                border-radius: 16px;
                padding: 14px 16px;
                box-shadow: 0 10px 25px rgba(11, 38, 35, 0.25);
            }
            .mock-due {
                position: static;
                margin-top: 12px;
                width: 100%;
                max-width: none;
                border-radius: 16px;
                padding: 14px 16px;
                box-shadow: 0 10px 25px rgba(11, 38, 35, 0.15);
            }
            .mock-grade {
                position: static;
                display: inline-flex;
                margin-bottom: 12px;
                box-shadow: none;
            }
            .mock-caption { display: block; text-align: center; margin-top: 10px; }
            .marquee-item { font-size: 16px; }
            .footer-grid { grid-template-columns: 1fr; gap: 32px; }
            .cta-box { padding: 36px 18px; border-radius: 20px; }
            .cta-actions { flex-direction: column; width: 100%; }
            .cta-actions .btn { width: 100%; justify-content: center; }
            .tabs-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; padding-bottom: 4px; }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after { animation-duration: 0.001s !important; animation-iteration-count: 1 !important; transition-duration: 0.001s !important; }
            .marquee-track { animation: none; }
            .js .reveal { opacity: 1; translate: 0 0; }
            .hl { background-size: 100% 100%; }
        }
    </style>
</head>
<body>

    <div class="progress" id="progress"></div>

    <!-- ================= NAVBAR ================= -->
    <header class="navbar" id="navbar">
        <div class="container nav-inner">
            <a href="index.php" class="brand" aria-label="AcadNexus home">
                <span class="brand-mark">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f4b63f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.42a12.08 12.08 0 01.67 6.48A11.95 11.95 0 0112 20.05a11.95 11.95 0 01-6.82-3 12.08 12.08 0 01.66-6.48L12 14z"/></svg>
                </span>
                <span class="brand-name">AcadNexus</span>
            </a>

            <ul class="nav-menu" id="navMenu">
                <li><a href="#features" class="nav-link">Features</a></li>
                <li><a href="#how-it-works" class="nav-link">How it works</a></li>
                <li><a href="#roles" class="nav-link">Students and faculty</a></li>
                <li><a href="login.php" class="nav-link">Log in</a></li>
            </ul>

            <div class="nav-actions">
                <a href="login.php" class="btn btn-ghost btn-sm btn-login">Log in</a>
                <a href="student/register.php" class="btn btn-primary btn-sm">Create account</a>
                <button class="hamburger" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="navMenu">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>
    </header>

    <main>
    <!-- ================= HERO ================= -->
    <section class="hero" id="home">
        <div class="hero-bg" aria-hidden="true">
            <div class="blob blob-1"></div>
            <div class="blob blob-2"></div>
            <div class="blob blob-3"></div>
        </div>

        <div class="container hero-grid">
            <div>
                <div class="hero-kicker"><b><span class="live-dot"></span>New term</b> Everything your campus runs on, together</div>
                <h1 class="hero-title">Your <span class="hl">whole semester</span>, in one place.</h1>
                <p class="hero-desc">
                    AcadNexus connects students and faculty in a single workspace. Hand in assignments, watch attendance, read announcements, book a mentor and check results without switching tools.
                </p>
                <div class="hero-buttons">
                    <a href="login.php" class="btn btn-primary">Log in to your workspace
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <a href="#features" class="btn btn-ghost">See what's inside</a>
                </div>
                <ul class="hero-checks">
                    <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Assignments and grading</li>
                    <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Attendance alerts</li>
                    <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Results and progress</li>
                </ul>
            </div>

            <!-- Product preview -->
            <div class="mock" aria-label="Sample dashboard preview">
                <div class="mock-grade float-c"><span>A-</span> Mid-term posted</div>

                <div class="mock-window">
                    <div class="mock-bar">
                        <div class="mock-dots"><i></i><i></i><i></i></div>
                        <strong>Today</strong>
                        <span>Monday</span>
                    </div>
                    <div class="mock-rows">
                        <div class="mock-row">
                            <div class="mock-time">9:00</div>
                            <div class="mock-class"><i style="background:#0f766e;"></i><div><b>Database Systems</b><small>Room B-204</small></div></div>
                            <span class="pill pill-teal">Present</span>
                        </div>
                        <div class="mock-row">
                            <div class="mock-time">11:00</div>
                            <div class="mock-class"><i style="background:#f4b63f;"></i><div><b>Operating Systems</b><small>Lab 3</small></div></div>
                            <span class="pill pill-amber">Notes added</span>
                        </div>
                        <div class="mock-row">
                            <div class="mock-time">2:00</div>
                            <div class="mock-class"><i style="background:#e4572e;"></i><div><b>Mentor meeting</b><small>Faculty cabin</small></div></div>
                            <span class="pill pill-ink">Booked</span>
                        </div>
                    </div>
                </div>

                <div class="mock-att float-a">
                    <div class="ring-wrap">
                        <svg viewBox="0 0 80 80" aria-hidden="true">
                            <circle class="track" cx="40" cy="40" r="34" fill="none" stroke-width="8"/>
                            <circle class="bar ring-anim" cx="40" cy="40" r="34" fill="none" stroke-width="8" stroke-dasharray="213.6" stroke-dashoffset="29.9"/>
                        </svg>
                        <div class="ring-num">86%</div>
                    </div>
                    <div><b>Attendance</b><small>Above the 75% minimum</small></div>
                </div>

                <div class="mock-due float-b">
                    <small>Due tomorrow, 11:59 PM</small>
                    <b>ER diagram and schema design</b>
                    <span class="btn btn-primary btn-sm" style="pointer-events:none;">Submit file</span>
                </div>
                <span class="mock-caption">Sample preview</span>
            </div>
        </div>
    </section>

    <!-- ================= PERKS ================= -->
    <section class="perks" aria-label="Highlights">
        <div class="container perks-grid">
            <div class="perk reveal" style="--d:0s;">
                <div class="perk-ico"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg></div>
                <div><b>Learning resources</b><small>Slides, notes and references</small></div>
            </div>
            <div class="perk reveal" style="--d:0.08s;">
                <div class="perk-ico"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
                <div><b>Faculty and peers</b><small>Book time, collaborate</small></div>
            </div>
            <div class="perk reveal" style="--d:0.16s;">
                <div class="perk-ico"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
                <div><b>Academic progress</b><small>Marks, GPA and charts</small></div>
            </div>
            <div class="perk reveal" style="--d:0.24s;">
                <div class="perk-ico"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                <div><b>Stay organised</b><small>Deadlines, alerts, notices</small></div>
            </div>
        </div>
    </section>

    <!-- ================= MARQUEE ================= -->
    <div class="marquee" aria-label="Platform features">
        <div class="marquee-track">
            <?php for ($i = 0; $i < 2; $i++): ?>
                <?php foreach ($ticker as $item): ?>
                    <span class="marquee-item" <?php echo $i === 1 ? 'aria-hidden="true"' : ''; ?>>
                        <?php echo htmlspecialchars($item); ?>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.4 7.6L22 12l-7.6 2.4L12 22l-2.4-7.6L2 12l7.6-2.4z"/></svg>
                    </span>
                <?php endforeach; ?>
            <?php endfor; ?>
        </div>
    </div>

    <!-- ================= FEATURES (BENTO) ================= -->
    <section class="section" id="features">
        <div class="container">
            <div class="section-head reveal">
                <span class="section-pill"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.4 7.6L22 12l-7.6 2.4L12 22l-2.4-7.6L2 12l7.6-2.4z"/></svg>Everything in one login</span>
                <h2 class="section-title">Everything a semester asks of you, already organised</h2>
                <p class="section-sub">Each part of academic life has its own place, and they all share the same login.</p>
            </div>

            <div class="bento">
                <!-- Assignments -->
                <article class="tile t-assign reveal" style="--d:0s;">
                    <div class="tile-ico"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
                    <h3>Assignments</h3>
                    <p>Faculty post work with deadlines. Students upload files, and uploads are validated automatically. Grades and feedback come back to the same page.</p>
                    <div class="tile-visual">
                        <div class="list-ui">
                            <div class="list-item"><div><b>Normalisation worksheet</b><small>Database Systems</small></div><span class="pill pill-teal">Submitted</span></div>
                            <div class="list-item"><div><b>Process scheduling report</b><small>Operating Systems</small></div><span class="pill pill-coral">Due tomorrow</span></div>
                            <div class="list-item"><div><b>Sorting algorithms quiz</b><small>Data Structures</small></div><span class="pill pill-amber">Graded 18/20</span></div>
                        </div>
                    </div>
                </article>

                <!-- Attendance -->
                <article class="tile t-attend t-dark reveal" style="--d:0.08s;">
                    <div class="ring-wrap" style="flex-shrink:0; width:96px; height:96px;">
                        <svg viewBox="0 0 80 80" aria-hidden="true">
                            <circle class="track" cx="40" cy="40" r="34" fill="none" stroke-width="8"/>
                            <circle class="bar" cx="40" cy="40" r="34" fill="none" stroke-width="8" stroke-dasharray="213.6" stroke-dashoffset="48"/>
                        </svg>
                        <div class="ring-num" style="font-size:22px;">78%</div>
                    </div>
                    <div>
                        <h3>Attendance</h3>
                        <p>Course-wise percentages update after every class, and a warning appears before a subject drops below the threshold.</p>
                    </div>
                </article>

                <!-- Announcements -->
                <article class="tile t-announce reveal" style="--d:0.16s;">
                    <div class="tile-ico"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg></div>
                    <h3>Announcements</h3>
                    <p>Exam schedules and department notices reach the right batch, with priority labels so nothing urgent gets buried.</p>
                    <div class="tile-visual">
                        <div class="list-ui">
                            <div class="list-item"><div><b>Mid-term timetable released</b></div><span class="pill pill-coral">Urgent</span></div>
                            <div class="list-item"><div><b>Library hours extended</b></div><span class="pill pill-teal">Notice</span></div>
                        </div>
                    </div>
                </article>

                <!-- Materials -->
                <article class="tile t-materials reveal" style="--d:0s;">
                    <div class="tile-ico"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg></div>
                    <h3>Course materials</h3>
                    <p>Slides, PDFs and reference notes live with the course, not in a chat thread.</p>
                    <div class="tile-visual">
                        <div class="chip-row">
                            <span class="chip file"><em style="background:#e4572e;">PDF</em>Unit 3 notes</span>
                            <span class="chip file"><em style="background:#f4b63f; color:#0b2623;">PPT</em>Lecture 8</span>
                            <span class="chip file"><em style="background:#0f766e;">DOC</em>Lab guide</span>
                        </div>
                    </div>
                </article>

                <!-- Appointments -->
                <article class="tile t-appoint t-tint reveal" style="--d:0.08s;">
                    <div class="tile-ico"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                    <h3>Faculty appointments</h3>
                    <p>Pick an open slot with a professor for guidance, doubt-clearing or mentoring.</p>
                    <div class="tile-visual">
                        <div class="chip-row">
                            <span class="chip">10:00</span>
                            <span class="chip on">11:30</span>
                            <span class="chip">2:00</span>
                            <span class="chip">4:15</span>
                        </div>
                    </div>
                </article>

                <!-- Progress -->
                <article class="tile t-progress reveal" style="--d:0.16s;">
                    <div class="tile-ico"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
                    <h3>Academic progress</h3>
                    <p>Charts show how your performance moves from one semester to the next.</p>
                    <div class="tile-visual">
                        <div class="bars" aria-hidden="true">
                            <span style="height:45%;"></span><span style="height:58%;"></span><span style="height:52%;"></span><span style="height:74%;"></span><span style="height:88%;"></span>
                        </div>
                        <div class="bars-labels"><span>S1</span><span>S2</span><span>S3</span><span>S4</span><span>S5</span></div>
                    </div>
                </article>

                <!-- Projects -->
                <article class="tile t-projects reveal" style="--d:0s;">
                    <div class="tile-ico"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg></div>
                    <h3>Project mentorship</h3>
                    <p>Track milestones and collect advisor feedback in one running thread.</p>
                    <div class="tile-visual">
                        <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; color:var(--ink);"><span>Smart attendance app</span><span>65%</span></div>
                        <div class="rail"><i style="--w:65%; width:65%;"></i></div>
                        <span class="pill pill-teal">Milestone 3 of 5</span>
                    </div>
                </article>

                <!-- Results -->
                <article class="tile t-results reveal" style="--d:0.08s;">
                    <div class="tile-ico"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg></div>
                    <h3>Exams and results</h3>
                    <p>Internal marks, assignment grades and your GPA, as soon as they're published.</p>
                    <div class="tile-visual">
                        <table class="mini-table" aria-hidden="true">
                            <tr><td>Database Systems</td><td>A</td></tr>
                            <tr><td>Operating Systems</td><td>A-</td></tr>
                            <tr><td>Data Structures</td><td>B+</td></tr>
                        </table>
                    </div>
                </article>

                <!-- Digital pass -->
                <article class="tile t-pass t-dark reveal" style="--d:0.16s;">
                    <div class="tile-ico"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg></div>
                    <h3>Digital student pass</h3>
                    <p>A scannable ID with your roll number and batch, plus a PDF you can download.</p>
                    <div class="tile-visual">
                        <div class="qr-mini" aria-hidden="true">
                            <svg viewBox="0 0 21 21" width="100%" height="100%" shape-rendering="crispEdges" fill="#0b2623">
                                <path d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"/>
                                <path d="M8 0h2v2H8zM11 1h2v2h-2zM8 4h2v2H8zM10 8h2v2h-2zM8 10h2v2H8zM13 9h2v2h-2zM16 9h2v2h-2zM19 10h2v2h-2zM12 12h2v2h-2zM15 13h2v2h-2zM18 14h2v2h-2zM9 14h2v2H9zM12 16h2v2h-2zM15 17h2v2h-2zM18 18h2v2h-2zM9 18h2v2H9zM0 8h2v2H0zM3 9h2v2H3zM6 8h2v2H6zM4 11h2v2H4zM1 12h2v2H1zM12 5h2v2h-2z"/>
                            </svg>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- ================= HOW IT WORKS ================= -->
    <section class="section how" id="how-it-works">
        <div class="container how-grid">
            <div class="reveal">
                <span class="section-pill"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.4 7.6L22 12l-7.6 2.4L12 22l-2.4-7.6L2 12l7.6-2.4z"/></svg>Quick start</span>
                <h2 class="section-title">Up and running in three steps</h2>
                <p class="section-sub">No setup calls and no spreadsheets to import. Sign up, join your courses and start using it the same day.</p>
                <div style="margin-top:30px; display:flex; gap:12px; flex-wrap:wrap;">
                    <a href="student/register.php" class="btn btn-primary">Register as student</a>
                    <a href="faculty/register.php" class="btn btn-ghost">Register as faculty</a>
                </div>
            </div>

            <div class="steps">
                <div class="step reveal" style="--d:0s;">
                    <div class="step-num">1</div>
                    <div>
                        <h3>Create your account</h3>
                        <p>Register as a student or faculty member with your department, course and roll ID.</p>
                    </div>
                </div>
                <div class="step reveal" style="--d:0.1s;">
                    <div class="step-num">2</div>
                    <div>
                        <h3>Connect and learn</h3>
                        <p>Open course materials, submit assignments, book time with faculty and join academic projects.</p>
                    </div>
                </div>
                <div class="step reveal" style="--d:0.2s;">
                    <div class="step-num">3</div>
                    <div>
                        <h3>Track your progress</h3>
                        <p>Watch attendance against the threshold, check exam results and see your growth on the dashboard.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= ROLES ================= -->
    <section class="section" id="roles">
        <div class="container">
            <div class="section-head reveal">
                <span class="section-pill"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.4 7.6L22 12l-7.6 2.4L12 22l-2.4-7.6L2 12l7.6-2.4z"/></svg>Built for both sides</span>
                <h2 class="section-title">One hub, two workspaces</h2>
                <p class="section-sub">Students and faculty see the tools that matter to them.</p>
            </div>

            <div class="reveal">
                <div class="tabs" role="tablist" aria-label="Choose a workspace">
                    <button class="tab" role="tab" id="tab-student" aria-selected="true" aria-controls="panel-student" data-target="panel-student">Student</button>
                    <button class="tab" role="tab" id="tab-faculty" aria-selected="false" aria-controls="panel-faculty" data-target="panel-faculty">Faculty</button>
                </div>

                <!-- Student -->
                <div class="role-panel active" id="panel-student" role="tabpanel" aria-labelledby="tab-student">
                    <div class="role-copy">
                        <h3>Student workspace</h3>
                        <p>Know what's due, how you're doing and who to ask for help.</p>
                        <ul class="role-list">
                            <li><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Learn and access course materials</li>
                            <li><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Submit assignments before the deadline</li>
                            <li><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Collaborate on projects with mentors</li>
                            <li><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Track attendance, marks and GPA</li>
                        </ul>
                        <div class="role-actions">
                            <a href="login.php" class="btn btn-amber">Student log in</a>
                            <a href="student/register.php" class="btn btn-outline-light">Create student account</a>
                        </div>
                    </div>
                    <div class="role-visual" aria-hidden="true">
                        <div class="rv-card"><div><b>Assignment due</b><small>ER diagram, tomorrow</small></div><span class="pill pill-coral">1 day</span></div>
                        <div class="rv-card"><div><b>Attendance</b><small>Database Systems</small></div><span class="pill pill-teal">86%</span></div>
                        <div class="rv-card"><div><b>Latest result</b><small>Operating Systems mid-term</small></div><span class="pill pill-amber">A-</span></div>
                    </div>
                </div>

                <!-- Faculty -->
                <div class="role-panel" id="panel-faculty" role="tabpanel" aria-labelledby="tab-faculty">
                    <div class="role-copy">
                        <h3>Faculty workspace</h3>
                        <p>Run your classes without chasing paperwork.</p>
                        <ul class="role-list">
                            <li><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Manage enrolled classes and materials</li>
                            <li><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Grade submissions with feedback</li>
                            <li><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Mark attendance and enter marks</li>
                            <li><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>Guide student projects and appointments</li>
                        </ul>
                        <div class="role-actions">
                            <a href="login.php" class="btn btn-amber">Faculty log in</a>
                            <a href="faculty/register.php" class="btn btn-outline-light">Create faculty account</a>
                        </div>
                    </div>
                    <div class="role-visual" aria-hidden="true">
                        <div class="rv-card"><div><b>Submissions to review</b><small>Operating Systems, Section A</small></div><span class="pill pill-amber">12 new</span></div>
                        <div class="rv-card"><div><b>Today's attendance</b><small>Database Systems</small></div><span class="pill pill-teal">Ready</span></div>
                        <div class="rv-card"><div><b>Appointment requests</b><small>Project guidance</small></div><span class="pill pill-coral">3 pending</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= CTA ================= -->
    <section class="cta">
        <div class="container">
            <div class="cta-box reveal">
                <div>
                    <h2>Make this semester the organised one.</h2>
                    <p>Join the students and faculty already running their coursework from a single hub.</p>
                </div>
                <div class="cta-actions">
                    <a href="student/register.php" class="btn btn-amber">Create an account</a>
                    <a href="login.php" class="btn btn-outline-light">I already have one</a>
                </div>
            </div>
        </div>
    </section>
    </main>

    <!-- ================= FOOTER ================= -->
    <footer class="footer">
        <div class="footer-mark" aria-hidden="true">AcadNexus</div>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="index.php" class="brand">
                        <span class="brand-mark">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f4b63f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.42a12.08 12.08 0 01.67 6.48A11.95 11.95 0 0112 20.05a11.95 11.95 0 01-6.82-3 12.08 12.08 0 01.66-6.48L12 14z"/></svg>
                        </span>
                        <span class="brand-name">AcadNexus</span>
                    </a>
                    <p>The unified student–faculty academic hub. Connect, learn, collaborate and grow.</p>
                </div>
                <div>
                    <h4>Explore</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How it works</a></li>
                        <li><a href="#roles">Workspaces</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Portals</h4>
                    <ul>
                        <li><a href="login.php">Log in</a></li>
                        <li><a href="student/register.php">Student registration</a></li>
                        <li><a href="faculty/register.php">Faculty registration</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">Privacy policy</a></li>
                        <li><a href="#">Terms of service</a></li>
                        <li><a href="#">Campus support</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span>&copy; <?php echo date('Y'); ?> AcadNexus. All rights reserved.</span>
                <span>Connect. Learn. Collaborate. Grow.</span>
            </div>
        </div>
    </footer>

    <button class="to-top" id="toTop" aria-label="Back to top">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
    </button>

    <script>
        // Mobile navigation
        const navToggle = document.getElementById('navToggle');
        const navMenu = document.getElementById('navMenu');
        navToggle.addEventListener('click', () => {
            const open = navMenu.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        navMenu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
            navMenu.classList.remove('open');
            navToggle.setAttribute('aria-expanded', 'false');
        }));

        // Student / faculty tabs
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.setAttribute('aria-selected', t === tab ? 'true' : 'false'));
                document.querySelectorAll('.role-panel').forEach(p => p.classList.toggle('active', p.id === tab.dataset.target));
            });
        });

        // Scroll progress, sticky navbar shadow, back-to-top
        const progress = document.getElementById('progress');
        const navbar = document.getElementById('navbar');
        const toTop = document.getElementById('toTop');
        let ticking = false;
        function onScroll() {
            const y = window.scrollY;
            const max = document.documentElement.scrollHeight - window.innerHeight;
            progress.style.transform = 'scaleX(' + (max > 0 ? Math.min(1, y / max) : 0) + ')';
            navbar.classList.toggle('scrolled', y > 10);
            toTop.classList.toggle('show', y > 700);
            ticking = false;
        }
        window.addEventListener('scroll', () => {
            if (!ticking) { requestAnimationFrame(onScroll); ticking = true; }
        }, { passive: true });
        onScroll();
        toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

        // Reveal on scroll
        const revealEls = document.querySelectorAll('.reveal');
        if ('IntersectionObserver' in window) {
            const io = new IntersectionObserver((entries) => {
                entries.forEach(e => {
                    if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
            revealEls.forEach(el => io.observe(el));
        } else {
            revealEls.forEach(el => el.classList.add('in'));
        }

        // Cursor spotlight on feature tiles
        document.querySelectorAll('.tile').forEach(tile => {
            tile.addEventListener('mousemove', (e) => {
                const r = tile.getBoundingClientRect();
                tile.style.setProperty('--mx', (e.clientX - r.left) + 'px');
                tile.style.setProperty('--my', (e.clientY - r.top) + 'px');
            });
        });
    </script>
</body>
</html>