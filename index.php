<?php
// index.php - AcadNexus Landing Page: Clinical Quartz Theme (Steel Prussian + Frosted Glaze + Teal)
session_start();

$ticker = [
    'Assignments & Submissions', 'Biometric & RFID Attendance', 'Course Syllabus & Repositories', 'Official University Circulars',
    'Faculty Advisory & Office Hours', 'Real-time GPA Tracking', 'Capstone Mentorship',
    'Automated Exam Transcripts', 'Encrypted Digital Campus Pass'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AcadNexus – Academic Intelligence & Faculty Governance Platform</title>
    <meta name="description" content="AcadNexus is a clinical-grade unified academic workspace designed for university faculty, researchers, and students. Coursework, grading, advisory, and telemetry in one clinical quartz interface.">
    <meta name="theme-color" content="#0a192f">
    <script>document.documentElement.classList.add('js');</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        /* ==========================================================================
           CLINICAL QUARTZ DESIGN SYSTEM
           Steel Prussian (#0a192f, #132742) + Frosted Glaze + Clinical Teal (#0ea5e9, #14b8a6)
           ========================================================================== */
        :root {
            /* Clinical Prussian Spectrum */
            --prussian-950: #060d19;
            --prussian-900: #0a192f;
            --prussian-850: #0f223d;
            --prussian-800: #132a4a;
            --prussian-700: #1e3a5f;
            --prussian-600: #2d4f7c;

            /* Clinical Teal & Cyan Accents */
            --teal-primary: #0d9488;
            --teal-bright: #14b8a6;
            --teal-cyan: #06b6d4;
            --teal-sky: #0284c7;
            --teal-glow: rgba(20, 184, 166, 0.28);
            --teal-subtle: rgba(13, 148, 136, 0.08);

            /* Frosted Glaze & Quartz Surfaces */
            --quartz-bg: #f8fafc;
            --quartz-surface: #ffffff;
            --quartz-frosted: rgba(255, 255, 255, 0.78);
            --quartz-border: #e2e8f0;
            --quartz-border-subtle: #edf2f7;
            --quartz-glass-border: rgba(255, 255, 255, 0.45);
            --quartz-shadow-sm: 0 4px 12px rgba(10, 25, 47, 0.04);
            --quartz-shadow-md: 0 12px 32px -6px rgba(10, 25, 47, 0.08);
            --quartz-shadow-lg: 0 24px 48px -12px rgba(10, 25, 47, 0.14);

            /* Typography & Neutrals */
            --text-heading: #0a192f;
            --text-body: #334155;
            --text-muted: #64748b;
            --text-light: #94a3b8;

            /* Clinical Badges */
            --badge-success-bg: #ecfdf5;
            --badge-success-text: #059669;
            --badge-warning-bg: #fefce8;
            --badge-warning-text: #ca8a04;
            --badge-critical-bg: #fef2f2;
            --badge-critical-text: #dc2626;

            /* Fonts & Radii */
            --font-main: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --radius-sm: 8px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --radius-xl: 28px;
        }

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 86px;
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
            -webkit-text-size-adjust: 100%;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--quartz-bg);
            color: var(--text-body);
            line-height: 1.6;
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
            position: relative;
            -webkit-font-smoothing: antialiased;
        }

        ::selection {
            background: rgba(20, 184, 166, 0.2);
            color: var(--prussian-900);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        :focus-visible {
            outline: 2px solid var(--teal-primary);
            outline-offset: 3px;
            border-radius: 6px;
        }

        .container {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 28px;
            box-sizing: border-box;
        }

        /* ---------- Telemetry Scroll Indicator ---------- */
        .progress-telemetry {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            width: 100%;
            background: linear-gradient(90deg, var(--teal-cyan), var(--teal-bright), #38bdf8);
            transform-origin: left;
            transform: scaleX(0);
            z-index: 1200;
        }

        /* ---------- Scroll Reveal ---------- */
        .js .reveal {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.65s cubic-bezier(0.16, 1, 0.3, 1) var(--d, 0s),
                        transform 0.65s cubic-bezier(0.16, 1, 0.3, 1) var(--d, 0s);
        }
        .js .reveal.in {
            opacity: 1;
            transform: translateY(0);
        }

        /* ---------- Buttons & Micro-Actions ---------- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 13px 24px;
            border-radius: var(--radius-md);
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
            letter-spacing: -0.2px;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-prussian {
            background: linear-gradient(135deg, var(--prussian-900) 0%, var(--prussian-800) 100%);
            color: #ffffff;
            box-shadow: 0 10px 24px -6px rgba(10, 25, 47, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .btn-prussian:hover {
            background: linear-gradient(135deg, var(--prussian-850) 0%, var(--prussian-700) 100%);
            box-shadow: 0 14px 28px -6px rgba(10, 25, 47, 0.45);
            transform: translateY(-2px);
        }

        .btn-teal {
            background: linear-gradient(135deg, var(--teal-primary) 0%, var(--teal-bright) 100%);
            color: #ffffff;
            box-shadow: 0 10px 22px -5px var(--teal-glow);
        }
        .btn-teal:hover {
            box-shadow: 0 14px 28px -4px rgba(20, 184, 166, 0.4);
            transform: translateY(-2px);
        }

        .btn-glaze {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            color: var(--prussian-900);
            border: 1px solid var(--quartz-border);
            box-shadow: var(--quartz-shadow-sm);
        }
        .btn-glaze:hover {
            background: #ffffff;
            border-color: var(--teal-primary);
            color: var(--teal-primary);
            transform: translateY(-2px);
            box-shadow: var(--quartz-shadow-md);
        }

        .btn-outline-prussian {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #ffffff;
            backdrop-filter: blur(10px);
        }
        .btn-outline-prussian:hover {
            border-color: #ffffff;
            background: rgba(255, 255, 255, 0.12);
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 9px 18px;
            font-size: 13.5px;
            border-radius: 10px;
        }

        /* ---------- Navbar: Frosted Glaze on Prussian Grid ---------- */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(248, 250, 252, 0.82);
            backdrop-filter: blur(18px) saturate(160%);
            -webkit-backdrop-filter: blur(18px) saturate(160%);
            border-bottom: 1px solid rgba(226, 232, 240, 0.85);
            transition: all 0.25s ease;
        }

        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.94);
            border-bottom-color: var(--quartz-border);
            box-shadow: 0 10px 30px -10px rgba(10, 25, 47, 0.07);
        }

        .nav-inner {
            height: 74px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-gem {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--prussian-900) 0%, var(--prussian-800) 50%, var(--teal-primary) 100%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--teal-bright);
            box-shadow: 0 8px 18px -4px rgba(10, 25, 47, 0.3);
            position: relative;
        }

        .brand-name {
            font-size: 21px;
            font-weight: 800;
            color: var(--prussian-900);
            letter-spacing: -0.6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .brand-badge {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 7px;
            border-radius: 6px;
            background: rgba(13, 148, 136, 0.12);
            color: var(--teal-primary);
            letter-spacing: 0.5px;
            border: 1px solid rgba(13, 148, 136, 0.2);
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 32px;
            list-style: none;
        }

        .nav-link {
            font-size: 14.5px;
            font-weight: 600;
            color: var(--text-muted);
            transition: color 0.18s ease;
            position: relative;
            padding: 6px 0;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--teal-primary);
            border-radius: 2px;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.22s ease;
        }

        .nav-link:hover {
            color: var(--prussian-900);
        }
        .nav-link:hover::after {
            transform: scaleX(1);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .hamburger {
            display: none;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 1px solid var(--quartz-border);
            background: #ffffff;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            color: var(--prussian-900);
            -webkit-tap-highlight-color: transparent;
        }

        /* ---------- HERO: Clinical Quartz Stage ---------- */
        .hero {
            padding: 140px 0 90px 0;
            position: relative;
            background: linear-gradient(180deg, rgba(241, 245, 249, 0.6) 0%, rgba(248, 250, 252, 1) 100%);
            overflow: hidden;
        }

        /* Precision Steel Grid Background Pattern */
        .hero-grid-bg {
            position: absolute;
            inset: 0;
            background-image: 
                radial-gradient(circle at 50% 0%, rgba(13, 148, 136, 0.08) 0%, transparent 60%),
                linear-gradient(to right, rgba(226, 232, 240, 0.45) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(226, 232, 240, 0.45) 1px, transparent 1px);
            background-size: 100% 100%, 48px 48px, 48px 48px;
            pointer-events: none;
        }

        .hero-stage {
            display: grid;
            grid-template-columns: 1.15fr 0.95fr;
            gap: 56px;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 6px 14px 6px 8px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid var(--quartz-border);
            box-shadow: var(--quartz-shadow-sm);
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 22px;
        }

        .hero-pill-badge {
            background: var(--prussian-900);
            color: var(--teal-bright);
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: var(--font-mono);
            letter-spacing: -0.2px;
        }

        .pulse-indicator {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--teal-bright);
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.3);
            animation: radarPulse 2s infinite;
        }
        @keyframes radarPulse {
            0% { box-shadow: 0 0 0 0 rgba(20, 184, 166, 0.5); }
            70% { box-shadow: 0 0 0 8px rgba(20, 184, 166, 0); }
            100% { box-shadow: 0 0 0 0 rgba(20, 184, 166, 0); }
        }

        .hero-title {
            font-size: clamp(38px, 4.8vw, 62px);
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -1.8px;
            color: var(--prussian-900);
            margin-bottom: 22px;
        }

        .hero-title .teal-gradient-text {
            background: linear-gradient(135deg, var(--teal-primary) 0%, var(--teal-bright) 50%, var(--teal-cyan) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            font-size: 18px;
            color: var(--text-muted);
            line-height: 1.65;
            max-width: 540px;
            margin-bottom: 34px;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 36px;
        }

        .hero-specs {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            list-style: none;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .hero-specs li {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hero-specs svg {
            color: var(--teal-primary);
            flex-shrink: 0;
        }

        /* ---------- HERO TELEMETRY CONSOLE (Interactive Mock) ---------- */
        .console-wrap {
            position: relative;
            max-width: 100%;
        }

        .console-glow {
            position: absolute;
            inset: -15px;
            background: radial-gradient(circle, rgba(13, 148, 136, 0.18) 0%, rgba(10, 25, 47, 0.05) 60%, transparent 80%);
            filter: blur(28px);
            z-index: 1;
            pointer-events: none;
        }

        .console-window {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--quartz-border);
            border-radius: var(--radius-xl);
            box-shadow: 0 28px 64px -16px rgba(10, 25, 47, 0.16);
            overflow: hidden;
            transition: transform 0.4s ease;
        }

        .console-header {
            padding: 16px 20px;
            background: linear-gradient(to right, #f8fafc, #ffffff);
            border-bottom: 1px solid var(--quartz-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .console-dots {
            display: flex;
            gap: 6px;
        }
        .console-dots span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #cbd5e1;
            display: block;
        }
        .console-dots span:nth-child(1) { background: #f87171; }
        .console-dots span:nth-child(2) { background: #fbbf24; }
        .console-dots span:nth-child(3) { background: #34d399; }

        .console-title {
            font-size: 13px;
            font-family: var(--font-mono);
            font-weight: 600;
            color: var(--prussian-900);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .console-badge-live {
            background: #ecfdf5;
            color: #059669;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            border: 1px solid #a7f3d0;
        }

        .console-body {
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Metric Glance Cards Inside Console */
        .console-glance-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .c-stat-box {
            background: #f8fafc;
            border: 1px solid var(--quartz-border);
            border-radius: var(--radius-md);
            padding: 12px 14px;
        }
        .c-stat-lbl {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }
        .c-stat-val {
            font-size: 20px;
            font-weight: 800;
            color: var(--prussian-900);
            display: flex;
            align-items: baseline;
            gap: 4px;
        }
        .c-stat-val small {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--teal-primary);
        }

        /* Course Stream Rows */
        .c-stream-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .c-stream-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            background: #ffffff;
            border: 1px solid var(--quartz-border);
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
        }
        .c-stream-item:hover {
            border-color: var(--teal-primary);
            box-shadow: 0 4px 14px rgba(10, 25, 47, 0.05);
            transform: translateX(2px);
        }

        .c-stream-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .c-stream-ico {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: var(--teal-subtle);
            color: var(--teal-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .c-stream-text strong {
            display: block;
            font-size: 13.5px;
            color: var(--prussian-900);
            font-weight: 700;
        }
        .c-stream-text span {
            font-size: 12px;
            color: var(--text-muted);
        }

        .c-status-chip {
            font-size: 11.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 8px;
            white-space: nowrap;
        }
        .chip-teal { background: #ccfbf1; color: #0f766e; }
        .chip-sky { background: #e0f2fe; color: #0369a1; }
        .chip-amber { background: #fef3c7; color: #b45309; }

        /* Floating Overlays on Desktop */
        .float-quartz-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(14px);
            border: 1px solid var(--quartz-border);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            box-shadow: var(--quartz-shadow-md);
            display: flex;
            align-items: center;
            gap: 14px;
            z-index: 3;
        }

        .fq-att {
            margin-top: 14px;
            background: linear-gradient(135deg, var(--prussian-900) 0%, var(--prussian-800) 100%);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.12);
        }
        .fq-att-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(20, 184, 166, 0.18);
            border: 2px solid var(--teal-bright);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-mono);
            font-weight: 800;
            font-size: 13px;
            color: var(--teal-bright);
        }

        /* ---------- RUNNING TELEMETRY TICKER ---------- */
        .ticker-strip {
            background: var(--prussian-900);
            padding: 14px 0;
            overflow: hidden;
            position: relative;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .ticker-strip::before, .ticker-strip::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 100px;
            z-index: 2;
            pointer-events: none;
        }
        .ticker-strip::before { left: 0; background: linear-gradient(to right, var(--prussian-900), transparent); }
        .ticker-strip::after { right: 0; background: linear-gradient(to left, var(--prussian-900), transparent); }

        .ticker-track {
            display: flex;
            width: max-content;
            animation: tickerScroll 34s linear infinite;
        }
        .ticker-track:hover {
            animation-play-state: paused;
        }
        @keyframes tickerScroll {
            to { transform: translateX(-50%); }
        }

        .ticker-unit {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-right: 32px;
            font-size: 13.5px;
            font-weight: 600;
            color: #94a3b8;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }
        .ticker-unit svg {
            color: var(--teal-bright);
            flex-shrink: 0;
        }

        /* ---------- SECTION HEADINGS ---------- */
        .section {
            padding: 96px 0;
            position: relative;
        }

        .section-header {
            max-width: 680px;
            margin-bottom: 48px;
        }

        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 4px 12px;
            border-radius: 999px;
            background: var(--teal-subtle);
            color: var(--teal-primary);
            font-size: 12.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border: 1px solid rgba(13, 148, 136, 0.2);
            margin-bottom: 14px;
        }

        .section-heading {
            font-size: clamp(30px, 3.6vw, 44px);
            font-weight: 800;
            line-height: 1.14;
            letter-spacing: -1.2px;
            color: var(--prussian-900);
            margin-bottom: 14px;
        }

        .section-subtext {
            font-size: 17px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ---------- BENTO SUITE: Clinical Quartz Tiles ---------- */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 20px;
        }

        .q-tile {
            background: #ffffff;
            border: 1px solid var(--quartz-border);
            border-radius: var(--radius-xl);
            padding: 28px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            box-shadow: var(--quartz-shadow-sm);
            transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .q-tile::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(420px circle at var(--mx, 50%) var(--my, 50%), rgba(20, 184, 166, 0.08), transparent 50%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .q-tile:hover::before { opacity: 1; }

        .q-tile:hover {
            border-color: rgba(20, 184, 166, 0.6);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(10, 25, 47, 0.1);
        }

        .q-tile-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--teal-subtle);
            color: var(--teal-primary);
            border: 1px solid rgba(13, 148, 136, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .q-tile h3 {
            font-size: 20px;
            font-weight: 800;
            color: var(--prussian-900);
            letter-spacing: -0.4px;
            margin-bottom: 8px;
        }

        .q-tile p {
            font-size: 14.5px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .q-tile-visual {
            margin-top: auto;
            padding-top: 22px;
        }

        /* Tile Spans */
        .tile-span-3 { grid-column: span 3; }
        .tile-span-2 { grid-column: span 2; }

        /* Dark Steel Tile Accent */
        .q-tile-prussian {
            background: linear-gradient(145deg, var(--prussian-900) 0%, var(--prussian-850) 100%);
            border-color: var(--prussian-800);
            color: #ffffff;
            box-shadow: 0 16px 36px -10px rgba(10, 25, 47, 0.35);
        }
        .q-tile-prussian h3 { color: #ffffff; }
        .q-tile-prussian p { color: #94a3b8; }
        .q-tile-prussian .q-tile-icon {
            background: rgba(20, 184, 166, 0.15);
            border-color: rgba(20, 184, 166, 0.3);
            color: var(--teal-bright);
        }

        /* Micro UI Inside Tiles */
        .q-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .q-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 14px;
            background: #f8fafc;
            border-radius: 10px;
            font-size: 13px;
            border: 1px solid var(--quartz-border-subtle);
        }
        .q-list-item b {
            color: var(--prussian-900);
            font-weight: 700;
        }

        /* Telemetry Bars */
        .tele-chart-bars {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            height: 80px;
            padding-top: 10px;
        }
        .tele-bar {
            flex: 1;
            background: linear-gradient(to top, var(--teal-primary), var(--teal-cyan));
            border-radius: 6px 6px 2px 2px;
            opacity: 0.35;
            display: block;
            transform-origin: bottom;
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .tele-bar:last-child {
            opacity: 1;
        }
        .tele-bar:hover {
            opacity: 0.9;
        }

        /* ---------- UNIVERSITY ARCHITECTURE / HOW IT WORKS ---------- */
        .section-workflow {
            background: #ffffff;
            border-top: 1px solid var(--quartz-border);
            border-bottom: 1px solid var(--quartz-border);
        }

        .wf-grid {
            display: grid;
            grid-template-columns: 0.85fr 1.15fr;
            gap: 60px;
            align-items: start;
        }

        .wf-steps {
            display: flex;
            flex-direction: column;
            gap: 32px;
            position: relative;
        }
        .wf-steps::before {
            content: '';
            position: absolute;
            left: 23px;
            top: 20px;
            bottom: 20px;
            width: 2px;
            background: linear-gradient(to bottom, var(--teal-primary), var(--prussian-700), var(--quartz-border));
        }

        .wf-step {
            display: grid;
            grid-template-columns: 48px 1fr;
            gap: 20px;
            position: relative;
            padding: 14px 18px 14px 0;
            border-radius: var(--radius-md);
            transition: background 0.2s ease;
        }
        .wf-step:hover {
            background: #f8fafc;
        }

        .wf-step-idx {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--prussian-900);
            color: var(--teal-bright);
            font-family: var(--font-mono);
            font-size: 16px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #ffffff;
            box-shadow: 0 0 0 3px rgba(10, 25, 47, 0.1);
            position: relative;
            z-index: 2;
        }

        .wf-step h3 {
            font-size: 20px;
            font-weight: 800;
            color: var(--prussian-900);
            margin-bottom: 6px;
        }
        .wf-step p {
            font-size: 15px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ---------- DUAL ROLE HUBS: Student & Faculty Tabs ---------- */
        .role-switch-nav {
            display: inline-flex;
            background: #ffffff;
            border: 1px solid var(--quartz-border);
            border-radius: 14px;
            padding: 5px;
            gap: 6px;
            margin-bottom: 28px;
            box-shadow: var(--quartz-shadow-sm);
        }

        .role-tab-btn {
            font-family: var(--font-main);
            font-size: 14.5px;
            font-weight: 700;
            padding: 10px 24px;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .role-tab-btn:hover {
            color: var(--prussian-900);
        }
        .role-tab-btn[aria-selected="true"] {
            background: var(--prussian-900);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(10, 25, 47, 0.2);
        }

        .workspace-panel {
            display: none;
            grid-template-columns: 1.15fr 0.85fr;
            background: linear-gradient(135deg, var(--prussian-950) 0%, var(--prussian-900) 60%, var(--prussian-850) 100%);
            border-radius: var(--radius-xl);
            color: #ffffff;
            overflow: hidden;
            box-shadow: 0 28px 64px -16px rgba(10, 25, 47, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .workspace-panel.active {
            display: grid;
            animation: panelFade 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes panelFade {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .ws-body {
            padding: 48px;
        }
        .ws-body h3 {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.8px;
            margin-bottom: 12px;
            color: #ffffff;
        }
        .ws-body > p {
            font-size: 16px;
            color: #94a3b8;
            margin-bottom: 28px;
            max-width: 480px;
        }

        .ws-checklist {
            list-style: none;
            display: grid;
            gap: 14px;
            margin-bottom: 34px;
        }
        .ws-checklist li {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            color: #e2e8f0;
        }
        .ws-checklist svg {
            color: var(--teal-bright);
            flex-shrink: 0;
        }

        .ws-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .ws-aside {
            background: rgba(255, 255, 255, 0.03);
            border-left: 1px solid rgba(255, 255, 255, 0.08);
            padding: 42px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 14px;
        }

        .ws-tele-pill {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: all 0.2s ease;
        }
        .ws-tele-pill:hover {
            transform: translateX(4px);
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(20, 184, 166, 0.4);
        }
        .ws-tele-pill strong {
            display: block;
            font-size: 14.5px;
            color: #ffffff;
        }
        .ws-tele-pill small {
            font-size: 12px;
            color: #94a3b8;
        }

        /* ---------- CLINICAL QUARTZ CTA ---------- */
        .cta-section {
            padding: 20px 0 96px 0;
        }

        .cta-quartz-box {
            background: linear-gradient(135deg, var(--prussian-900) 0%, var(--prussian-850) 60%, var(--teal-primary) 100%);
            border-radius: var(--radius-xl);
            padding: 64px 56px;
            position: relative;
            overflow: hidden;
            color: #ffffff;
            display: grid;
            grid-template-columns: 1.3fr 0.7fr;
            gap: 40px;
            align-items: center;
            box-shadow: 0 32px 72px -16px rgba(10, 25, 47, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .cta-quartz-box::before {
            content: '';
            position: absolute;
            right: -80px;
            top: -80px;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(20, 184, 166, 0.3) 0%, transparent 70%);
            pointer-events: none;
        }

        .cta-quartz-box h2 {
            font-size: clamp(30px, 3.8vw, 46px);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1.2px;
            margin-bottom: 14px;
        }

        .cta-quartz-box p {
            font-size: 17px;
            color: #cbd5e1;
            max-width: 500px;
            line-height: 1.6;
        }

        .cta-btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            position: relative;
            z-index: 2;
        }

        /* ---------- UNIVERSITY FOOTER ---------- */
        .footer {
            background: var(--prussian-950);
            color: #94a3b8;
            padding: 72px 0 28px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            position: relative;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 48px;
            margin-bottom: 48px;
        }

        .footer-brand-col p {
            margin-top: 16px;
            font-size: 14.5px;
            line-height: 1.6;
            max-width: 320px;
            color: #64748b;
        }

        .footer-col h4 {
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 18px;
        }

        .footer-col ul {
            list-style: none;
            display: grid;
            gap: 11px;
        }

        .footer-col ul a {
            font-size: 14px;
            color: #94a3b8;
            transition: all 0.18s ease;
        }
        .footer-col ul a:hover {
            color: var(--teal-bright);
            padding-left: 3px;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.07);
            padding-top: 24px;
            font-size: 13.5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            color: #64748b;
        }

        /* Back to top floating button */
        .to-top {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 900;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1px solid var(--quartz-border);
            cursor: pointer;
            background: #ffffff;
            color: var(--prussian-900);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--quartz-shadow-md);
            opacity: 0;
            pointer-events: none;
            transform: translateY(12px);
            transition: all 0.25s ease;
        }
        .to-top.show {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }
        .to-top:hover {
            background: var(--teal-primary);
            color: #ffffff;
            border-color: var(--teal-primary);
        }

        /* ==========================================================================
           ANDROID & MOBILE SCREEN ADAPTATIONS (Zero Horizontal Drag)
           ========================================================================== */
        @media (max-width: 1040px) {
            .hero-stage { grid-template-columns: 1fr; gap: 42px; }
            .console-wrap { max-width: 100%; margin: 0 auto; }
            .bento-grid { grid-template-columns: repeat(2, 1fr); }
            .tile-span-3, .tile-span-2 { grid-column: span 1; }
            .wf-grid { grid-template-columns: 1fr; gap: 40px; }
            .workspace-panel.active { grid-template-columns: 1fr; }
            .ws-aside { border-left: none; border-top: 1px solid rgba(255, 255, 255, 0.08); }
            .cta-quartz-box { grid-template-columns: 1fr; padding: 48px 32px; }
            .footer-grid { grid-template-columns: 1fr 1fr; gap: 36px; }
        }

        @media (max-width: 820px) {
            .hamburger {
                display: inline-flex;
            }
            .nav-actions .btn-glaze {
                display: none;
            }
            .nav-menu {
                display: none;
                position: fixed;
                top: 74px;
                left: 0;
                right: 0;
                bottom: 0;
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
                padding: 20px;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(24px);
                -webkit-backdrop-filter: blur(24px);
                border-top: 1px solid var(--quartz-border);
                overflow-y: auto;
                z-index: 999;
                box-shadow: var(--quartz-shadow-lg);
            }
            .nav-menu.open {
                display: flex;
                animation: mobileDrawerIn 0.25s ease forwards;
            }
            @keyframes mobileDrawerIn {
                from { opacity: 0; transform: translateY(-8px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .nav-menu li {
                width: 100%;
                list-style: none;
            }
            .nav-link {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 13px 16px;
                font-size: 15.5px;
                font-weight: 700;
                color: var(--prussian-900);
                border-radius: var(--radius-md);
                background: #f8fafc;
                border: 1px solid var(--quartz-border);
            }
            .nav-link:hover, .nav-link:active {
                background: var(--teal-subtle);
                color: var(--teal-primary);
                border-color: rgba(13, 148, 136, 0.3);
            }
            .nav-link::after { display: none; }
        }

        @media (max-width: 640px) {
            .container { padding: 0 16px; }
            .section { padding: 60px 0; }
            .hero { padding: 100px 0 50px 0; }
            .hero-title {
                font-size: 32px;
                letter-spacing: -1.2px;
                line-height: 1.15;
            }
            .hero-desc {
                font-size: 15.5px;
                margin-bottom: 24px;
            }
            .hero-actions {
                flex-direction: column;
                width: 100%;
                gap: 10px;
            }
            .hero-actions .btn {
                width: 100%;
                justify-content: center;
            }
            .hero-specs {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .bento-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }
            .q-tile {
                padding: 22px 18px;
                border-radius: var(--radius-lg);
            }
            .ws-body, .ws-aside {
                padding: 28px 18px;
            }
            .ws-body h3 {
                font-size: 24px;
            }
            .ws-actions {
                flex-direction: column;
                width: 100%;
            }
            .ws-actions .btn {
                width: 100%;
                justify-content: center;
            }
            .cta-quartz-box {
                padding: 36px 20px;
                border-radius: var(--radius-lg);
            }
            .cta-btn-group {
                width: 100%;
            }
            .cta-btn-group .btn {
                width: 100%;
                justify-content: center;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 32px;
            }
            .footer-bottom {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>

    <!-- Scroll Progress Indicator -->
    <div class="progress-telemetry" id="progress"></div>

    <!-- ================= NAVBAR: Clinical Quartz ================= -->
    <header class="navbar" id="navbar">
        <div class="container nav-inner">
            <a href="index.php" class="brand" aria-label="AcadNexus Home">
                <span class="brand-gem">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path d="M12 14l6.16-3.42a12.08 12.08 0 01.67 6.48A11.95 11.95 0 0112 20.05a11.95 11.95 0 01-6.82-3 12.08 12.08 0 01.66-6.48L12 14z"/>
                    </svg>
                </span>
                <span class="brand-name">
                    AcadNexus
                    <span class="brand-badge">Clinical</span>
                </span>
            </a>

            <ul class="nav-menu" id="navMenu">
                <li><a href="#features" class="nav-link">Governance Suite</a></li>
                <li><a href="#how-it-works" class="nav-link">Architecture</a></li>
                <li><a href="#roles" class="nav-link">Faculty & Students</a></li>
                <li><a href="login.php" class="nav-link">Secure Portal</a></li>
            </ul>

            <div class="nav-actions">
                <a href="login.php" class="btn btn-prussian btn-sm">Log in</a>
                <button class="hamburger" id="navToggle" aria-label="Toggle navigation drawer" aria-expanded="false" aria-controls="navMenu">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <main>
    <!-- ================= HERO SECTION ================= -->
    <section class="hero" id="home">
        <div class="hero-grid-bg" aria-hidden="true"></div>

        <div class="container hero-stage">
            <div>
                <div class="hero-pill">
                    <span class="hero-pill-badge">
                        <span class="pulse-indicator"></span>
                        CAMPUS TELEMETRY ACTIVE
                    </span>
                    Unified Academic Governance Platform
                </div>
                
                <h1 class="hero-title">
                    Clinical Precision for <br>
                    <span class="teal-gradient-text">Higher Education.</span>
                </h1>
                
                <p class="hero-desc">
                    AcadNexus bridges university faculty, departmental heads, and students into a high-trust academic hub. Submissions, automated attendance auditing, research mentorship, and grade sheets in one clinical interface.
                </p>

                <div class="hero-actions">
                    <a href="login.php" class="btn btn-prussian">
                        Access Academic Portal
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6"/>
                        </svg>
                    </a>
                    <a href="#features" class="btn btn-glaze">Explore Governance Suite</a>
                </div>

                <ul class="hero-specs">
                    <li>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6L9 17l-5-5"/>
                        </svg>
                        Multi-Factor Faculty Access
                    </li>
                    <li>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6L9 17l-5-5"/>
                        </svg>
                        Continuous Attendance Auditing
                    </li>
                    <li>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6L9 17l-5-5"/>
                        </svg>
                        Encrypted File Verification
                    </li>
                </ul>
            </div>

            <!-- Clinical Quartz Telemetry Console Preview -->
            <div class="console-wrap" aria-label="Live academic telemetry preview">
                <div class="console-glow"></div>
                <div class="console-window">
                    <div class="console-header">
                        <div class="console-dots">
                            <span></span><span></span><span></span>
                        </div>
                        <div class="console-title">
                            ACADEMIC_OPS // REALTIME_CONSOLE
                        </div>
                        <span class="console-badge-live">● SYSTEM HEALTHY</span>
                    </div>

                    <div class="console-body">
                        <!-- Top Metrics -->
                        <div class="console-glance-row">
                            <div class="c-stat-box">
                                <div class="c-stat-lbl">Department Quorum</div>
                                <div class="c-stat-val">94.8% <small>▲</small></div>
                            </div>
                            <div class="c-stat-box">
                                <div class="c-stat-lbl">Submissions</div>
                                <div class="c-stat-val">128 <small>Clean</small></div>
                            </div>
                            <div class="c-stat-box">
                                <div class="c-stat-lbl">Faculty Cohort</div>
                                <div class="c-stat-val">24 <small>Active</small></div>
                            </div>
                        </div>

                        <!-- Course Streams -->
                        <div class="c-stream-list">
                            <div class="c-stream-item">
                                <div class="c-stream-info">
                                    <div class="c-stream-ico">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                    </div>
                                    <div class="c-stream-text">
                                        <strong>Advanced Database Engines</strong>
                                        <span>CS-401 • Room B-204 Lecture Hall</span>
                                    </div>
                                </div>
                                <span class="c-status-chip chip-teal">Audited</span>
                            </div>

                            <div class="c-stream-item">
                                <div class="c-stream-info">
                                    <div class="c-stream-ico">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <div class="c-stream-text">
                                        <strong>Kernel Architecture Lab</strong>
                                        <span>CS-403 • 42 Student Submissions</span>
                                    </div>
                                </div>
                                <span class="c-status-chip chip-sky">Grading</span>
                            </div>

                            <div class="c-stream-item">
                                <div class="c-stream-info">
                                    <div class="c-stream-ico">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div class="c-stream-text">
                                        <strong>Faculty Office Hours Slot</strong>
                                        <span>Cabin 402 • Prof. Sarah Jenkins</span>
                                    </div>
                                </div>
                                <span class="c-status-chip chip-amber">Reserved</span>
                            </div>
                        </div>

                        <!-- Mini Micro telemetry card -->
                        <div class="float-quartz-card fq-att">
                            <div class="fq-att-circle">91%</div>
                            <div>
                                <strong style="font-size:13.5px; display:block;">Cohort Compliance Index</strong>
                                <small style="color:#94a3b8; font-size:12px;">Validated against statutory university standards</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= RUNNING TICKER STRIP ================= -->
    <div class="ticker-strip" aria-label="System components">
        <div class="ticker-track">
            <?php for ($i = 0; $i < 2; $i++): ?>
                <?php foreach ($ticker as $item): ?>
                    <div class="ticker-unit" <?php echo $i === 1 ? 'aria-hidden="true"' : ''; ?>>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l2.4 7.6L22 12l-7.6 2.4L12 22l-2.4-7.6L2 12l7.6-2.4z"/>
                        </svg>
                        <span><?php echo htmlspecialchars($item); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endfor; ?>
        </div>
    </div>

    <!-- ================= GOVERNANCE SUITE (BENTO GRID) ================= -->
    <section class="section" id="features">
        <div class="container">
            <div class="section-header reveal">
                <span class="section-tag">Clinical Quartz Architecture</span>
                <h2 class="section-heading">Designed for rigorous university governance</h2>
                <p class="section-subtext">Eliminating disparate spreadsheets, lost attachments, and manual attendance logs with an integrated academic registry.</p>
            </div>

            <div class="bento-grid">
                <!-- 1. Assignments Tile -->
                <article class="q-tile tile-span-3 reveal" style="--d:0s;">
                    <div class="q-tile-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3>Coursework Evaluation & Submissions</h3>
                    <p>Faculty formulate assignments with strict deadlines and MIME-type integrity checks. Students upload deliverables with timestamped signatures, and grades reflect immediately in transcripts.</p>
                    <div class="q-tile-visual">
                        <div class="q-list">
                            <div class="q-list-item">
                                <div><b>Relational Algebra Schema</b><br><small style="color:var(--text-muted);">Database Systems • Prof. Miller</small></div>
                                <span class="c-status-chip chip-teal">Graded (96/100)</span>
                            </div>
                            <div class="q-list-item">
                                <div><b>Distributed Lock Protocol</b><br><small style="color:var(--text-muted);">Operating Systems • Due 23:59</small></div>
                                <span class="c-status-chip chip-amber">In Review</span>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- 2. Attendance Tile -->
                <article class="q-tile tile-span-3 reveal" style="--d:0.08s;">
                    <div class="q-tile-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3>Automated Attendance Telemetry</h3>
                    <p>Live session auditing calculates cohort percentage against the mandatory 75% university quorum. Students with deficit trajectories receive automated notices before exams.</p>
                    <div class="q-tile-visual">
                        <div style="background:#f8fafc; border:1px solid var(--quartz-border); border-radius:var(--radius-md); padding:16px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <span style="font-size:13px; font-weight:700; color:var(--prussian-900);">Cohort Attendance Ratio</span>
                                <strong style="font-family:var(--font-mono); color:var(--teal-primary);">88.4% Quorum</strong>
                            </div>
                            <div style="height:8px; background:#e2e8f0; border-radius:99px; overflow:hidden;">
                                <div style="width:88.4%; height:100%; background:linear-gradient(90deg, var(--teal-primary), var(--teal-bright)); border-radius:99px;"></div>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- 3. Syllabus Repositories -->
                <article class="q-tile tile-span-2 reveal" style="--d:0.16s;">
                    <div class="q-tile-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M4 19.5A2.5 2.5 0 016.5 17H20M4 4.5A2.5 2.5 0 016.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15z"/>
                        </svg>
                    </div>
                    <h3>Course Repositories</h3>
                    <p>Centralized distribution of curriculum slides, lab guides, and research references with strict file verification.</p>
                </article>

                <!-- 4. Faculty Advisory -->
                <article class="q-tile tile-span-2 reveal" style="--d:0.24s;">
                    <div class="q-tile-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3>Faculty Advisory Desk</h3>
                    <p>Dedicated appointment booking prevents email chaos and protects faculty office hours with automated scheduling.</p>
                </article>

                <!-- 5. Academic Performance -->
                <article class="q-tile tile-span-2 reveal" style="--d:0.32s;">
                    <div class="q-tile-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                    <h3>Performance Telemetry</h3>
                    <p>Departmental marks distribution and automated GPA computation rendered via clinical vector graphs.</p>
                    <div class="q-tile-visual">
                        <div class="tele-chart-bars">
                            <span class="tele-bar" style="height:45%;"></span>
                            <span class="tele-bar" style="height:65%;"></span>
                            <span class="tele-bar" style="height:55%;"></span>
                            <span class="tele-bar" style="height:80%;"></span>
                            <span class="tele-bar" style="height:92%;"></span>
                        </div>
                    </div>
                </article>

                <!-- 6. Project Mentorship -->
                <article class="q-tile tile-span-2 reveal" style="--d:0.4s;">
                    <div class="q-tile-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <h3>Capstone Mentorship</h3>
                    <p>Faculty-guided student project incubators with milestone trackers, supervisor approvals, and deliverable reviews.</p>
                </article>

                <!-- 7. Official Announcements -->
                <article class="q-tile tile-span-2 reveal" style="--d:0.48s;">
                    <div class="q-tile-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                        </svg>
                    </div>
                    <h3>Departmental Bulletins</h3>
                    <p>Broadcast circulars with image notices and role-targeted dissemination for examinations and symposiums.</p>
                </article>

                <!-- 8. Digital Campus Pass (Prussian Card) -->
                <article class="q-tile q-tile-prussian tile-span-2 reveal" style="--d:0.56s;">
                    <div class="q-tile-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                        </svg>
                    </div>
                    <h3>Digital ID Pass</h3>
                    <p>Encrypted QR credential with roll number and program metadata, ready for instant PDF lanyard rendering.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ================= HOW IT WORKS: Architectural Flow ================= -->
    <section class="section section-workflow" id="how-it-works">
        <div class="container wf-grid">
            <div class="reveal">
                <span class="section-tag">Campus Deployment</span>
                <h2 class="section-heading">Frictionless onboarding in three steps</h2>
                <p class="section-subtext">No cumbersome enterprise procurement cycles or complex server configurations. AcadNexus deploys natively for university departments.</p>
                <div style="margin-top: 32px; display: flex; gap: 12px; flex-wrap: wrap;">
                    <a href="faculty/register.php" class="btn btn-prussian">Register Faculty Account</a>
                    <a href="student/register.php" class="btn btn-glaze">Register Student</a>
                </div>
            </div>

            <div class="wf-steps">
                <div class="wf-step reveal" style="--d:0s;">
                    <div class="wf-step-idx">01</div>
                    <div>
                        <h3>Institutional Registry</h3>
                        <p>Authenticate with your verified university department and institutional credentials for role validation.</p>
                    </div>
                </div>

                <div class="wf-step reveal" style="--d:0.1s;">
                    <div class="wf-step-idx">02</div>
                    <div>
                        <h3>Course Allocation & Syllabus Sync</h3>
                        <p>Faculty link assigned lecture cohorts, configure grade weights, and publish syllabus assets seamlessly.</p>
                    </div>
                </div>

                <div class="wf-step reveal" style="--d:0.2s;">
                    <div class="wf-step-idx">03</div>
                    <div>
                        <h3>Continuous Telemetry & Auditing</h3>
                        <p>Track student submissions, audit attendance compliance, and log marks directly into department records.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= DUAL ROLE WORKSPACES ================= -->
    <section class="section" id="roles">
        <div class="container">
            <div class="section-header reveal">
                <span class="section-tag">Role-Based Experience</span>
                <h2 class="section-heading">Dedicated workspaces for academic staff and scholars</h2>
                <p class="section-subtext">Tailored tools designed specifically for faculty management workflows and student learning telemetry.</p>
            </div>

            <div class="reveal">
                <div class="role-switch-nav" role="tablist" aria-label="Select role workspace">
                    <button class="role-tab-btn" role="tab" id="tab-faculty" aria-selected="true" aria-controls="panel-faculty" data-target="panel-faculty">Faculty Workspace</button>
                    <button class="role-tab-btn" role="tab" id="tab-student" aria-selected="false" aria-controls="panel-student" data-target="panel-student">Student Portal</button>
                </div>

                <!-- Faculty Workspace Panel -->
                <div class="workspace-panel active" id="panel-faculty" role="tabpanel" aria-labelledby="tab-faculty">
                    <div class="ws-body">
                        <h3>Faculty Governance Hub</h3>
                        <p>Designed for university professors, lecturers, and teaching assistants to conduct academic operations without administrative friction.</p>
                        
                        <ul class="ws-checklist">
                            <li>
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Course cohort roster management and syllabus deployment
                            </li>
                            <li>
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Assignment creation with dual-action inline viewing and direct download
                            </li>
                            <li>
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Instant session attendance marking and statutory deficit calculation
                            </li>
                            <li>
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Office hours advisory management with one-click status locking
                            </li>
                        </ul>

                        <div class="ws-actions">
                            <a href="login.php" class="btn btn-teal">Sign in as Faculty</a>
                            <a href="faculty/register.php" class="btn btn-outline-prussian">Faculty Registration</a>
                        </div>
                    </div>

                    <div class="ws-aside" aria-hidden="true">
                        <div class="ws-tele-pill">
                            <div>
                                <strong>Submissions Ready For Review</strong>
                                <small>Operating Systems • Section A</small>
                            </div>
                            <span class="c-status-chip chip-teal">12 Pending</span>
                        </div>

                        <div class="ws-tele-pill">
                            <div>
                                <strong>Daily Attendance Sheet</strong>
                                <small>Database Systems • Lecture 18</small>
                            </div>
                            <span class="c-status-chip chip-sky">Recorded</span>
                        </div>

                        <div class="ws-tele-pill">
                            <div>
                                <strong>Advisory Appointments</strong>
                                <small>Research Mentorship</small>
                            </div>
                            <span class="c-status-chip chip-amber">2 Scheduled</span>
                        </div>
                    </div>
                </div>

                <!-- Student Workspace Panel -->
                <div class="workspace-panel" id="panel-student" role="tabpanel" aria-labelledby="tab-student">
                    <div class="ws-body">
                        <h3>Student Academic Dashboard</h3>
                        <p>A clean, transparent view of your semester's progress, upcoming milestones, and direct communication channels with your instructors.</p>
                        
                        <ul class="ws-checklist">
                            <li>
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                One-click syllabus access and curated course reference libraries
                            </li>
                            <li>
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Timestamped homework and project deliverable submission
                            </li>
                            <li>
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Continuous attendance tracking with shortage alerts before 75%
                            </li>
                            <li>
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Digital student lanyard pass with verification QR and PDF export
                            </li>
                        </ul>

                        <div class="ws-actions">
                            <a href="login.php" class="btn btn-teal">Sign in as Student</a>
                            <a href="student/register.php" class="btn btn-outline-prussian">Student Registration</a>
                        </div>
                    </div>

                    <div class="ws-aside" aria-hidden="true">
                        <div class="ws-tele-pill">
                            <div>
                                <strong>Upcoming Assignment</strong>
                                <small>ER Model Specification</small>
                            </div>
                            <span class="c-status-chip chip-amber">Due Tomorrow</span>
                        </div>

                        <div class="ws-tele-pill">
                            <div>
                                <strong>Semester Attendance</strong>
                                <small>Safe above 75% threshold</small>
                            </div>
                            <span class="c-status-chip chip-teal">86% Safe</span>
                        </div>

                        <div class="ws-tele-pill">
                            <div>
                                <strong>Published Exam Grade</strong>
                                <small>Software Engineering Mid-term</small>
                            </div>
                            <span class="c-status-chip chip-sky">Grade: A-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= CLINICAL QUARTZ CTA ================= -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-quartz-box reveal">
                <div>
                    <h2>Elevate Your Institution's Academic Workflow.</h2>
                    <p>Experience the certainty and elegance of a clinical-grade academic hub. Built specifically for universities, verified by faculty, and loved by students.</p>
                </div>
                <div class="cta-btn-group">
                    <a href="student/register.php" class="btn btn-teal">Create Student Account</a>
                    <a href="faculty/register.php" class="btn btn-outline-prussian">Faculty Onboarding</a>
                    <a href="login.php" class="btn btn-glaze">Existing Portal Sign In</a>
                </div>
            </div>
        </div>
    </section>
    </main>

    <!-- ================= UNIVERSITY FOOTER ================= -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand-col">
                    <a href="index.php" class="brand">
                        <span class="brand-gem">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M12 14l9-5-9-5-9 5 9 5z"/>
                                <path d="M12 14l6.16-3.42a12.08 12.08 0 01.67 6.48A11.95 11.95 0 0112 20.05a11.95 11.95 0 01-6.82-3 12.08 12.08 0 01.66-6.48L12 14z"/>
                            </svg>
                        </span>
                        <span class="brand-name" style="color:#ffffff;">AcadNexus</span>
                    </a>
                    <p>Clinical Quartz Academic Telemetry & Governance Suite. Standardizing higher education workflows with clinical precision.</p>
                </div>

                <div class="footer-col">
                    <h4>Platform</h4>
                    <ul>
                        <li><a href="#home">Executive Overview</a></li>
                        <li><a href="#features">Governance Suite</a></li>
                        <li><a href="#how-it-works">Architecture</a></li>
                        <li><a href="#roles">Faculty & Student Hubs</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Access Portals</h4>
                    <ul>
                        <li><a href="login.php">Institutional Login</a></li>
                        <li><a href="student/register.php">Student Registration</a></li>
                        <li><a href="faculty/register.php">Faculty Registration</a></li>
                        <li><a href="login.php">Administrative Console</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Governance</h4>
                    <ul>
                        <li><a href="#">Academic Ethics</a></li>
                        <li><a href="#">Security & Encryption</a></li>
                        <li><a href="#">Statutory Quorum Rules</a></li>
                        <li><a href="#">Help & IT Support</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <span>&copy; <?php echo date('Y'); ?> AcadNexus Academic Hub. Steel Prussian & Clinical Quartz Architecture.</span>
                <span>High-Trust University Software</span>
            </div>
        </div>
    </footer>

    <!-- Back to top floating button -->
    <button class="to-top" id="toTop" aria-label="Back to top">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 19V5M5 12l7-7 7 7"/>
        </svg>
    </button>

    <script>
        // Drawer Mobile Navigation
        const navToggle = document.getElementById('navToggle');
        const navMenu = document.getElementById('navMenu');
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', () => {
                const open = navMenu.classList.toggle('open');
                navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            navMenu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
                navMenu.classList.remove('open');
                navToggle.setAttribute('aria-expanded', 'false');
            }));
        }

        // Student / Faculty Workspace Tab Switcher
        const roleTabs = document.querySelectorAll('.role-tab-btn');
        roleTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                roleTabs.forEach(t => t.setAttribute('aria-selected', t === tab ? 'true' : 'false'));
                document.querySelectorAll('.workspace-panel').forEach(p => {
                    p.classList.toggle('active', p.id === tab.dataset.target);
                });
            });
        });

        // Scroll Progress & Navbar Elevation
        const progressEl = document.getElementById('progress');
        const navbarEl = document.getElementById('navbar');
        const toTopBtn = document.getElementById('toTop');
        let isTicking = false;

        function handleScrollTelemetry() {
            const y = window.scrollY;
            const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
            if (progressEl) {
                progressEl.style.transform = 'scaleX(' + (maxScroll > 0 ? Math.min(1, y / maxScroll) : 0) + ')';
            }
            if (navbarEl) {
                navbarEl.classList.toggle('scrolled', y > 15);
            }
            if (toTopBtn) {
                toTopBtn.classList.toggle('show', y > 600);
            }
            isTicking = false;
        }

        window.addEventListener('scroll', () => {
            if (!isTicking) {
                requestAnimationFrame(handleScrollTelemetry);
                isTicking = true;
            }
        }, { passive: true });
        handleScrollTelemetry();

        if (toTopBtn) {
            toTopBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        }

        // Intersection Observer Reveal Animations
        const revealElements = document.querySelectorAll('.reveal');
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(e => {
                    if (e.isIntersecting) {
                        e.target.classList.add('in');
                        observer.unobserve(e.target);
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
            revealElements.forEach(el => observer.observe(el));
        } else {
            revealElements.forEach(el => el.classList.add('in'));
        }

        // Spotlight Glow on Bento Tiles
        document.querySelectorAll('.q-tile').forEach(tile => {
            tile.addEventListener('mousemove', (e) => {
                const rect = tile.getBoundingClientRect();
                tile.style.setProperty('--mx', (e.clientX - rect.left) + 'px');
                tile.style.setProperty('--my', (e.clientY - rect.top) + 'px');
            });
        });
    </script>
</body>
</html>