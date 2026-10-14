<?php require_once '../config/config.php';
requireLogin(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tree Health Monitoring Map</title>

    <!-- Google Fonts for better typography -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">


    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f6f8;
            color: #333;
        }

        .header {
            background-color: #005343;
            /* MangoCare Primary Color */
            color: white;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        h1 {
            margin: 0;
            font-size: 24px;
        }

        p.subtitle {
            margin: 5px 0 0;
            opacity: 0.8;
            font-size: 14px;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        #map-wrapper {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        #map {
            height: 850px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .legend-item {
            display: flex;
            align-items: center;
            font-size: 14px;
            font-weight: 600;
        }

        .dot {
            height: 15px;
            width: 15px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            border: 2px solid rgba(0, 0, 0, 0.2);
        }

        /* ── Schedule Modal Premium UI Design ── */
        #scheduleModal.modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            z-index: 999999;
            background: rgba(10, 30, 25, 0.65);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        #scheduleModal.modal.show {
            display: flex !important;
        }

        #scheduleModal .modal-content {
            background: #ffffff;
            border-radius: 20px;
            width: min(540px, 95%);
            padding: 0;
            overflow: hidden;
            box-shadow: 0 25px 60px -15px rgba(0, 71, 55, 0.35), 0 0 0 1px rgba(0, 71, 55, 0.08);
            position: relative;
            animation: modalPopIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modalPopIn {
            0% {
                transform: scale(0.92) translateY(18px);
                opacity: 0;
            }

            100% {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        /* Modal Header */
        #scheduleModal .sched-modal-header {
            background: linear-gradient(135deg, #004737 0%, #005A47 60%, #006B55 100%);
            padding: 1.5rem 1.75rem;
            color: #ffffff;
            position: relative;
            display: flex;
            align-items: center;
            gap: 1.15rem;
        }

        #scheduleModal .sched-modal-icon {
            width: 46px;
            height: 46px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #70B9A1;
            flex-shrink: 0;
            box-shadow: inset 0 0 10px rgba(255, 255, 255, 0.1);
        }

        #scheduleModal .sched-modal-title-box h2 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 0.2rem 0;
            border: none;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        #scheduleModal .sched-modal-subtitle {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
        }

        #scheduleModal .sched-modal-close {
            position: absolute;
            top: 1.15rem;
            right: 1.25rem;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        #scheduleModal .sched-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        /* Modal Body & Form */
        #scheduleModal .sched-modal-body {
            padding: 1.75rem;
        }

        #scheduleModal .sched-tree-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #EAF6F2;
            color: #004737;
            border: 1px solid #B8E2D5;
            padding: 0.35rem 0.85rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
        }

        /* Form Grid */
        #scheduleModal .sched-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 480px) {
            #scheduleModal .sched-form-row {
                grid-template-columns: 1fr;
            }
        }

        #scheduleModal .sched-form-group {
            margin-bottom: 1.15rem;
        }

        #scheduleModal .sched-form-group label {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: #2D3748;
            margin-bottom: 0.4rem;
        }

        #scheduleModal .sched-form-group label i {
            color: #006B55;
        }

        #scheduleModal .sched-input {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            font-size: 0.88rem;
            font-family: 'Inter', sans-serif;
            color: #1A202C;
            background: #F8FAFC;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        #scheduleModal .sched-input:focus {
            outline: none;
            border-color: #006B55;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(0, 107, 85, 0.12);
        }

        #scheduleModal textarea.sched-input {
            resize: vertical;
            min-height: 80px;
        }

        /* Quick Presets & Treatment Pills */
        #scheduleModal .sched-presets {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.15rem;
            flex-wrap: wrap;
        }

        #scheduleModal .sched-preset-btn {
            background: #F1F5F9;
            border: 1px solid #CBD5E1;
            color: #475569;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        #scheduleModal .sched-preset-btn:hover,
        #scheduleModal .sched-preset-btn.active {
            background: #004737;
            border-color: #004737;
            color: #ffffff;
        }

        #scheduleModal .sched-type-pills {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-bottom: 1.15rem;
        }

        #scheduleModal .sched-type-pill {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
        }

        #scheduleModal .sched-type-pill:hover {
            border-color: #006B55;
            background: #EAF6F2;
            color: #004737;
        }

        #scheduleModal .sched-type-pill.active {
            border-color: #004737;
            background: #004737;
            color: #ffffff;
        }

        /* Live Summary Box */
        #scheduleModal .sched-live-summary {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            color: #166534;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.83rem;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Footer Actions */
        #scheduleModal .sched-modal-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }

        #scheduleModal .btn-save-sched {
            flex: 2;
            background: linear-gradient(135deg, #005343 0%, #007A60 100%);
            color: #ffffff;
            border: none;
            padding: 0.85rem 1.25rem;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 14px rgba(0, 83, 67, 0.3);
            transition: all 0.2s ease;
        }

        #scheduleModal .btn-save-sched:hover {
            background: linear-gradient(135deg, #004737 0%, #006B55 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(0, 83, 67, 0.4);
        }

        #scheduleModal .btn-cancel-sched {
            flex: 1;
            background: #F8FAFC;
            color: #64748B;
            border: 1.5px solid #E2E8F0;
            padding: 0.85rem 1.25rem;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        #scheduleModal .btn-cancel-sched:hover {
            background: #F1F5F9;
            color: #1E293B;
            border-color: #CBD5E1;
        }

        /* ══════════════════════════════════════════════
           Create Tree Modal (Submission Approval)
        ══════════════════════════════════════════════ */
        #createTreeModal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000000;
            background: rgba(10, 30, 25, 0.65);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        #createTreeModal.active {
            display: flex !important;
        }

        #createTreeModal .ctm-modal-box {
            background: #fff;
            border-radius: 20px;
            width: min(580px, 96%);
            max-height: 92vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px -15px rgba(0, 71, 55, 0.35), 0 0 0 1px rgba(0, 71, 55, 0.08);
            animation: modalPopIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        #createTreeModal .ctm-header {
            background: linear-gradient(135deg, #1D4ED8 0%, #2563EB 60%, #3B82F6 100%);
            padding: 1.5rem 1.75rem;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-radius: 20px 20px 0 0;
        }

        #createTreeModal .ctm-icon {
            width: 46px;
            height: 46px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        #createTreeModal .ctm-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0 0 .2rem;
        }

        #createTreeModal .ctm-subtitle {
            font-size: .8rem;
            opacity: .85;
            margin: 0;
        }

        #createTreeModal .ctm-close {
            margin-left: auto;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all .2s;
        }

        #createTreeModal .ctm-close:hover {
            background: rgba(255, 255, 255, .3);
            transform: rotate(90deg);
        }

        #createTreeModal .ctm-body {
            padding: 1.75rem;
        }

        #createTreeModal .ctm-farmer-badge {
            display: flex;
            align-items: center;
            gap: .75rem;
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            border-radius: 12px;
            padding: .75rem 1rem;
            margin-bottom: 1.25rem;
        }

        #createTreeModal .ctm-farmer-badge .ctm-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1rem;
            flex-shrink: 0;
        }

        #createTreeModal .ctm-farmer-text {
            font-size: .84rem;
            color: #1e3a5f;
        }

        #createTreeModal .ctm-farmer-text strong {
            display: block;
            font-size: .9rem;
            color: #1e293b;
        }

        #createTreeModal .ctm-coords-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
            margin-bottom: 1.25rem;
        }

        #createTreeModal .ctm-coord-box {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 10px;
            padding: .6rem .9rem;
            font-size: .82rem;
            color: #166534;
        }

        #createTreeModal .ctm-coord-box span {
            font-size: .72rem;
            text-transform: uppercase;
            font-weight: 700;
            opacity: .75;
            display: block;
        }

        #createTreeModal .ctm-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media(max-width:480px) {
            #createTreeModal .ctm-form-row {
                grid-template-columns: 1fr;
            }
        }

        #createTreeModal .ctm-form-group {
            margin-bottom: 1rem;
        }

        #createTreeModal .ctm-form-group label {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .82rem;
            font-weight: 600;
            color: #2D3748;
            margin-bottom: .4rem;
        }

        #createTreeModal .ctm-form-group label i {
            color: #2563EB;
        }

        #createTreeModal .ctm-form-group label .req {
            color: #dc2626;
            margin-left: 2px;
        }

        #createTreeModal .ctm-input {
            width: 100%;
            padding: .7rem .9rem;
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            font-size: .88rem;
            font-family: 'Inter', sans-serif;
            color: #1A202C;
            background: #F8FAFC;
            transition: all .2s;
            box-sizing: border-box;
        }

        #createTreeModal .ctm-input:focus {
            outline: none;
            border-color: #2563EB;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .1);
        }

        #createTreeModal textarea.ctm-input {
            resize: vertical;
            min-height: 80px;
        }

        #createTreeModal .ctm-actions {
            display: flex;
            gap: .75rem;
            margin-top: 1.25rem;
        }

        #createTreeModal .ctm-btn-save {
            flex: 2;
            background: linear-gradient(135deg, #1D4ED8 0%, #2563EB 100%);
            color: #fff;
            border: none;
            padding: .85rem 1.25rem;
            border-radius: 10px;
            font-size: .92rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            box-shadow: 0 4px 14px rgba(29, 78, 216, .3);
            transition: all .2s;
        }

        #createTreeModal .ctm-btn-save:hover {
            background: linear-gradient(135deg, #1e40af, #1D4ED8);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(29, 78, 216, .4);
        }

        #createTreeModal .ctm-btn-save:disabled {
            opacity: .65;
            cursor: not-allowed;
            transform: none;
        }

        #createTreeModal .ctm-btn-cancel {
            flex: 1;
            background: #F8FAFC;
            color: #64748B;
            border: 1.5px solid #E2E8F0;
            padding: .85rem;
            border-radius: 10px;
            font-size: .92rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
        }

        #createTreeModal .ctm-btn-cancel:hover {
            background: #F1F5F9;
            color: #1E293B;
        }

        #createTreeModal .ctm-alert {
            padding: .75rem 1rem;
            border-radius: 10px;
            font-size: .85rem;
            margin-top: 1rem;
            display: none;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        #createTreeModal .ctm-alert-success {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            color: #166534;
        }

        #createTreeModal .ctm-alert-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #DC2626;
        }

        /* Reject Modal */
        #rejectModal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000001;
            background: rgba(10, 30, 25, .65);
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        #rejectModal.active {
            display: flex !important;
        }

        #rejectModal .rm-box {
            background: #fff;
            border-radius: 18px;
            width: min(440px, 96%);
            padding: 0;
            box-shadow: 0 25px 60px -15px rgba(220, 38, 38, .25);
            animation: modalPopIn .3s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
        }

        #rejectModal .rm-header {
            background: linear-gradient(135deg, #b91c1c, #dc2626);
            padding: 1.25rem 1.5rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        #rejectModal .rm-header i {
            font-size: 1.3rem;
        }

        #rejectModal .rm-header h2 {
            margin: 0;
            font-size: 1.1rem;
        }

        #rejectModal .rm-body {
            padding: 1.5rem;
        }

        #rejectModal .rm-body p {
            font-size: .9rem;
            color: #475569;
            margin: 0 0 1rem;
        }

        #rejectModal textarea.ctm-input {
            min-height: 90px;
        }

        #rejectModal .rm-actions {
            display: flex;
            gap: .75rem;
            margin-top: 1rem;
        }

        #rejectModal .rm-btn-reject {
            flex: 2;
            background: linear-gradient(135deg, #b91c1c, #dc2626);
            color: #fff;
            border: none;
            padding: .8rem;
            border-radius: 10px;
            font-size: .92rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
        }

        #rejectModal .rm-btn-cancel {
            flex: 1;
            background: #F8FAFC;
            color: #64748B;
            border: 1.5px solid #E2E8F0;
            padding: .8rem;
            border-radius: 10px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>MangoCare Tree Monitoring Map</h1>
        <p class="subtitle">System Defense Visualization (PHP + Leaflet.js)</p>
    </div>

    <div class="container">

        <div class="legend">
            <div class="legend-item"><span class="dot green-dot"></span> Healthy (< 2 Diseases)</div>
                    <div class="legend-item"><span class="dot yellow-dot"></span> Warning (2 - 4 Diseases)</div>
                    <div class="legend-item"><span class="dot red-dot"></span> Critical (≥ 5 Diseases)</div>
                    <div class="legend-item"><span class="dot" style="background:#2563EB;border-color:#1D4ED8;"></span>
                        Pending Submission</div>
            </div>

            <br>

            <div id="map-wrapper">
                <div id="map"></div>
            </div>

        </div>

        <!-- Google Maps API -->
        <script
            src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDrbSIXqYwXA00EgMbd-cRUKsHGCXv42Rg&libraries=geometry"></script>

        <script>
            let googleMap;
            let markers = [];

            // Initialize globals to avoid race conditions with initMap
            window.treeMarkers = window.treeMarkers || {};
            window.treeInfoWindows = window.treeInfoWindows || {};

            function initMap() {
                const defaultLocation = { lat: 7.3442, lng: 125.5725 };

                window.googleMap = new google.maps.Map(document.getElementById('map'), {
                    zoom: 13,
                    center: defaultLocation,
                    disableDefaultUI: false,
                    zoomControl: true,
                    streetViewControl: false,
                    mapTypeControl: true,
                    mapTypeId: 'hybrid'
                });

                // Handle Click-to-Mark
                window.googleMap.addListener('click', (e) => {
                    const lat = e.latLng.lat();
                    const lng = e.latLng.lng();

                    showConfirmModal(
                        "Register New Tree",
                        `Would you like to register a new mango tree at these GPS coordinates?<br><br><strong>Lat:</strong> ${lat.toFixed(5)}<br><strong>Lng:</strong> ${lng.toFixed(5)}`,
                        () => {
                            showPromptModal(
                                "Identify Tree",
                                "Please enter a unique identifier for this tree:",
                                "Tree-000",
                                (treeId) => {
                                    if (treeId && treeId.trim() !== '') {
                                        // Temporary marker
                                        const tempMarker = new google.maps.Marker({
                                            position: { lat, lng },
                                            map: window.googleMap,
                                            title: `Saving ${treeId}...`,
                                            icon: {
                                                path: google.maps.SymbolPath.CIRCLE,
                                                scale: 8,
                                                fillOpacity: 0.8,
                                                fillColor: '#2196F3',
                                                strokeColor: '#1976D2',
                                                strokeWeight: 2
                                            }
                                        });

                                        const infoWindow = new google.maps.InfoWindow({
                                            content: `<b>Saving Tree ${treeId}...</b>`
                                        });
                                        infoWindow.open(window.googleMap, tempMarker);

                                        // Save to Firebase via PHP
                                        fetch('add_tree.php', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ id: treeId, lat, lng })
                                        })
                                            .then(response => response.json())
                                            .then(data => {
                                                if (data.success) {
                                                    infoWindow.setContent(`
                                                <div style="padding:5px; text-align:center; font-family: 'Inter', sans-serif;">
                                                    <b style="color:#005343; font-size: 14px;">Tree ${treeId} Marked!</b><br>
                                                    <span style="font-size:11px; color:#666;">QR code auto-generated.</span><br>
                                                    <div style="margin-top: 10px;">
                                                        <a href="qr-generator.php" class="btn btn-sm btn-primary" style="display:inline-block; font-size:10px; padding:4px 8px; text-decoration:none; color:white; background: #005343; border-radius:4px;">
                                                            <i class="fas fa-print"></i> Go to QR Generator
                                                        </a>
                                                    </div>
                                                </div>
                                            `);
                                                } else {
                                                    infoWindow.setContent(`<b>Error:</b><br>${data.message}`);
                                                }
                                            })
                                            .catch(err => {
                                                console.error('Error:', err);
                                                infoWindow.setContent(`<b>Network Error</b>`);
                                            });
                                    }
                                }
                            );
                        }
                    );
                });

                // Modal Helper Functions
                function showConfirmModal(title, message, onConfirm) {
                    const modalHtml = `
                    <div class="custom-modal-overlay" id="confirmModal">
                        <div class="custom-modal-container">
                            <div class="custom-modal-header">
                                <i class="fas fa-map-marker-alt"></i>
                                <h2>${title}</h2>
                            </div>
                            <div class="custom-modal-body">${message}</div>
                            <div class="custom-modal-footer">
                                <button class="custom-modal-btn custom-modal-btn-cancel" id="confirmCancel">Cancel</button>
                                <button class="custom-modal-btn custom-modal-btn-confirm" id="confirmOk">Register Tree</button>
                            </div>
                        </div>
                    </div>
                `;
                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                    const modal = document.getElementById('confirmModal');
                    setTimeout(() => modal.classList.add('active'), 10);

                    const cleanup = () => {
                        modal.classList.remove('active');
                        setTimeout(() => modal.remove(), 300);
                    };

                    document.getElementById('confirmCancel').onclick = cleanup;
                    document.getElementById('confirmOk').onclick = () => {
                        cleanup();
                        onConfirm();
                    };
                }

                function showPromptModal(title, message, placeholder, onConfirm) {
                    const modalHtml = `
                    <div class="custom-modal-overlay" id="promptModal">
                        <div class="custom-modal-container">
                            <div class="custom-modal-header">
                                <i class="fas fa-edit"></i>
                                <h2>${title}</h2>
                            </div>
                            <div class="custom-modal-body">
                                ${message}
                                <div class="custom-modal-input-container">
                                    <input type="text" class="custom-modal-input" id="promptInput" placeholder="${placeholder}" autofocus>
                                </div>
                            </div>
                            <div class="custom-modal-footer">
                                <button class="custom-modal-btn custom-modal-btn-cancel" id="promptCancel">Cancel</button>
                                <button class="custom-modal-btn custom-modal-btn-confirm" id="promptOk">Confirm Details</button>
                            </div>
                        </div>
                    </div>
                `;
                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                    const modal = document.getElementById('promptModal');
                    const input = document.getElementById('promptInput');
                    setTimeout(() => {
                        modal.classList.add('active');
                        input.focus();
                    }, 10);

                    const cleanup = () => {
                        modal.classList.remove('active');
                        setTimeout(() => modal.remove(), 300);
                    };

                    document.getElementById('promptCancel').onclick = cleanup;
                    document.getElementById('promptOk').onclick = () => {
                        const val = input.value;
                        cleanup();
                        onConfirm(val);
                    };
                    input.onkeyup = (e) => {
                        if (e.key === 'Enter') document.getElementById('promptOk').click();
                    };
                }

                // Fetch Tree data (Initial load)
                fetch("get_trees.php?t=" + new Date().getTime())
                    .then(response => response.json())
                    .then(treeData => {
                        const bounds = new google.maps.LatLngBounds();
                        let hasMarkers = false;

                        for (let id in treeData) {
                            let tree = treeData[id];
                            let colorInfo = getMarkerColor(tree.diseaseCount || 0);

                            const marker = new google.maps.Marker({
                                position: { lat: tree.latitude, lng: tree.longitude },
                                map: window.googleMap,
                                icon: {
                                    path: google.maps.SymbolPath.CIRCLE,
                                    scale: 8,
                                    fillOpacity: 0.8,
                                    fillColor: colorInfo.color,
                                    strokeColor: colorInfo.stroke,
                                    strokeWeight: 2
                                }
                            });

                            // Store metadata on marker for update use
                            marker._treeId = id;
                            marker._diseaseCount = tree.diseaseCount || 0;
                            window.treeMarkers[id] = marker;

                            const infoWindow = new google.maps.InfoWindow({
                                content: updateInfoWindowContent(id, tree, tree.nextSpray)
                            });
                            window.treeInfoWindows[id] = infoWindow;

                            marker.addListener('mouseover', () => {
                                infoWindow.open(window.googleMap, marker);
                            });

                            bounds.extend(marker.getPosition());
                            hasMarkers = true;
                        }

                        if (hasMarkers) {
                            window.googleMap.fitBounds(bounds);
                        }

                        // Add "Show All Details" control
                        const controlDiv = document.createElement('div');
                        controlDiv.style.margin = '10px';
                        const controlUI = document.createElement('div');
                        controlUI.style.backgroundColor = '#fff';
                        controlUI.style.border = '2px solid #fff';
                        controlUI.style.borderRadius = '3px';
                        controlUI.style.boxShadow = '0 2px 6px rgba(0,0,0,.3)';
                        controlUI.style.cursor = 'pointer';
                        controlUI.style.textAlign = 'center';
                        controlUI.title = 'Click to toggle all tree details';
                        controlDiv.appendChild(controlUI);

                        const controlText = document.createElement('div');
                        controlText.style.color = 'rgb(25,25,25)';
                        controlText.style.fontFamily = 'Roboto,Arial,sans-serif';
                        controlText.style.fontSize = '14px';
                        controlText.style.lineHeight = '32px';
                        controlText.style.paddingLeft = '10px';
                        controlText.style.paddingRight = '10px';
                        controlText.innerHTML = '<i class="fas fa-eye"></i> Show All Details';
                        controlUI.appendChild(controlText);

                        window.googleMap.controls[google.maps.ControlPosition.TOP_RIGHT].push(controlDiv);

                        // Add Health Status Legend
                        const legendDiv = document.createElement('div');
                        legendDiv.style.margin = '10px';
                        legendDiv.style.backgroundColor = 'rgba(255, 255, 255, 0.9)';
                        legendDiv.style.padding = '10px';
                        legendDiv.style.borderRadius = '8px';
                        legendDiv.style.boxShadow = '0 2px 6px rgba(0,0,0,0.3)';
                        legendDiv.style.fontFamily = "'Inter', sans-serif";
                        legendDiv.style.fontSize = '12px';
                        legendDiv.innerHTML = `
                <div style="font-weight: bold; margin-bottom: 5px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 3px;">Map Legend</div>
                <div style="display: flex; align-items: center; margin-bottom: 3px;">
                    <span style="width: 12px; height: 12px; border-radius: 50%; background: #4caf50; display: inline-block; margin-right: 8px; border: 1px solid #388e3c;"></span>
                    <span>Healthy (&lt; 2 Diseases)</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 3px;">
                    <span style="width: 12px; height: 12px; border-radius: 50%; background: #F97316; display: inline-block; margin-right: 8px; border: 1px solid #ea580c;"></span>
                    <span>Spray Tomorrow</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 3px;">
                    <span style="width: 12px; height: 12px; border-radius: 50%; background: #3B82F6; display: inline-block; margin-right: 8px; border: 1px solid #2563eb;"></span>
                    <span>Upcoming Spray (1mo)</span>
                </div>
                <div style="display: flex; align-items: center; margin-bottom: 3px;">
                    <span style="width: 12px; height: 12px; border-radius: 50%; background: #ff9800; display: inline-block; margin-right: 8px; border: 1px solid #f57c00;"></span>
                    <span>Warning (2 - 4 Diseases)</span>
                </div>
                <div style="display: flex; align-items: center;">
                    <span style="width: 12px; height: 12px; border-radius: 50%; background: #d32f2f; display: inline-block; margin-right: 8px; border: 1px solid #b71c1c;"></span>
                    <span>Critical (&ge; 5 Diseases)</span>
                </div>
            `;
                        window.googleMap.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(legendDiv);

                        // Add styles for QR modal if not present
                        if (!document.getElementById('qrModalStyles')) {
                            const style = document.createElement('style');
                            style.id = 'qrModalStyles';
                            style.innerHTML = `
                    .qr-code-container { text-align: center; padding: 1.5rem; }
                    .qr-code-image { max-width: 260px; width: 100%; height: auto; margin: 1.25rem auto; display: block; border-radius: 12px; box-shadow: var(--shadow-card); border: 3px solid #fff; }
                    .qr-print-container { text-align: center; padding: 30px; background: white; }
                    .qr-print-details { margin-top: 20px; font-family: Arial, sans-serif; }
                    .qr-print-text { font-size: 18px; margin: 10px 0; color: #000; }
                    @media print {
                        body * { visibility: hidden; }
                        #printQRContainer, #printQRContainer * { visibility: visible; }
                        #printQRContainer { position: absolute; left: 0; top: 0; width: 100%; height: 100%; display: block !important; }
                        .qr-print-container { padding: 50px; background: white; }
                    }
                `;
                            document.head.appendChild(style);
                        }

                        window._allInfoWindowsVisible = false;

                        // Loop removed to prevent auto-showing on page load

                        controlUI.addEventListener('click', () => {
                            window._allInfoWindowsVisible = !window._allInfoWindowsVisible;
                            controlText.innerHTML = window._allInfoWindowsVisible ?
                                '<i class="fas fa-eye-slash"></i> Hide All Details' :
                                '<i class="fas fa-eye"></i> Show All Details';

                            for (let id in window.treeInfoWindows) {
                                if (window._allInfoWindowsVisible) {
                                    window.treeInfoWindows[id].open(window.googleMap, window.treeMarkers[id]);
                                } else {
                                    window.treeInfoWindows[id].close();
                                }
                            }
                        });
                    });
            }

            window.onload = function () {
                if (typeof google !== 'undefined') initMap();
            };

            // Firebase Real-time Updates for all trees
        </script>
        <script type="module">
            import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
            import { getDatabase, ref, onValue } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-database.js";

            // Firebase configuration from PHP
            const firebaseConfig = {
                apiKey: "<?php echo addslashes(FIREBASE_API_KEY); ?>",
                authDomain: "<?php echo addslashes(FIREBASE_AUTH_DOMAIN); ?>",
                databaseURL: "<?php echo addslashes(FIREBASE_DATABASE_URL); ?>",
                projectId: "<?php echo addslashes(FIREBASE_PROJECT_ID); ?>",
                storageBucket: "<?php echo addslashes(FIREBASE_STORAGE_BUCKET); ?>",
                messagingSenderId: "<?php echo addslashes(FIREBASE_MESSAGING_SENDER_ID); ?>",
                appId: "<?php echo addslashes(FIREBASE_APP_ID); ?>"
            };

            const app = initializeApp(firebaseConfig);
            const db = getDatabase(app);

            function formatNextSpray(schedule) {
                if (!schedule || !schedule.spray_date || !schedule.spray_time) return "No schedule set";

                const dateStr = schedule.spray_date;
                const timeStr = schedule.spray_time;
                const ts = new Date(dateStr + ' ' + timeStr);

                const dateFormatted = ts.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });

                // Convert to 12-hour
                let [h, m] = timeStr.split(':');
                h = parseInt(h);
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                const timeFormatted = `${h}:${m} ${ampm}`;

                return `${dateFormatted} at ${timeFormatted}`;
            }

            function getMarkerColor(diseaseCount, nextSprayDateStr) {
                const now = new Date();
                now.setHours(0, 0, 0, 0);
                const tomorrow = new Date(now);
                tomorrow.setDate(now.getDate() + 1);
                const oneMonthLater = new Date(now);
                oneMonthLater.setMonth(now.getMonth() + 1);

                let nextSprayDate = null;
                if (nextSprayDateStr) {
                    nextSprayDate = new Date(nextSprayDateStr);
                    nextSprayDate.setHours(0, 0, 0, 0);
                }

                if (diseaseCount >= 5) return { color: "#f44336", stroke: "#d32f2f", status: "Critical", bgColor: "#ffebee" };
                if (nextSprayDate && nextSprayDate.getTime() === tomorrow.getTime()) return { color: "#F97316", stroke: "#ea580c", status: "Spray Tomorrow", bgColor: "#fff7ed" };
                if (diseaseCount >= 2) return { color: "#ffb300", stroke: "#f57c00", status: "Warning", bgColor: "#fff3e0" };
                if (nextSprayDate && nextSprayDate <= oneMonthLater && nextSprayDate > now) return { color: "#3B82F6", stroke: "#2563eb", status: "Upcoming Spray", bgColor: "#eff6ff" };

                return { color: "#4caf50", stroke: "#388e3c", status: "Healthy", bgColor: "#e8f5e9" };
            }

            // Global store for markers and infowindows to allow real-time updates
            window.treeMarkers = window.treeMarkers || {};
            window.treeInfoWindows = window.treeInfoWindows || {};

            // Helper to update InfoWindow content
            function updateInfoWindowContent(id, tree, nextSpray) {
                const nextSprayDateStr = nextSpray ? nextSpray.spray_date : null;
                const diseaseInfo = getMarkerColor(tree.diseaseCount || 0, nextSprayDateStr);
                const nextSprayText = formatNextSpray(nextSpray);
                const sprayClass = nextSpray ? "badge-warning" : "badge-secondary";

                return `
                <div style="padding:5px; min-width:200px; font-family: 'Inter', sans-serif;">
                    <h4 style="margin:0 0 8px 0; color:#005343; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                        <i class="fas fa-leaf"></i> Tree Details
                    </h4>
                    <div style="margin-bottom: 5px;">
                        <span style="color: #666; font-size: 11px; text-transform: uppercase; display: block;">Tree Identifier:</span>
                        <strong style="color: #333; font-size: 14px;">${id}</strong>
                    </div>
                    <div style="margin-bottom: 8px;">
                        <span style="color: #666; font-size: 11px; text-transform: uppercase; display: block;">Health Status:</span>
                        <span style="padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: bold; background: ${diseaseInfo.bgColor}; color: ${diseaseInfo.stroke};">
                            <i class="fas ${tree.diseaseCount > 0 ? 'fa-virus' : 'fa-check-circle'}"></i> 
                            ${tree.diseaseCount || 0} Disease Scans Found
                        </span>
                    </div>
                    <div style="margin-bottom: 10px; border-top: 1px solid #f0f0f0; padding-top: 8px;">
                        <span style="color: #666; font-size: 11px; text-transform: uppercase; display: block;">Next Treatment:</span>
                        <span style="font-size: 13px; font-weight: 600; color: #1b5e20;">
                            <i class="fas fa-calendar-alt" style="margin-right: 4px;"></i> 
                            ${nextSprayText}
                        </span>
                    </div>
                    <div style="margin-top: 10px; border-top: 1px solid #f0f0f0; padding-top: 10px; text-align: center;">
                        ${tree.has_qr ? `
                            <button type="button" onmousedown="event.stopPropagation(); window._suppressMapClick=true;" onclick="showQRModal('${id}', '${tree.location || 'N/A'}', 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(JSON.stringify({tree_id: '${id}', location: '${tree.location || ''}', generated_at: ${tree.timestamp || Date.now()}})))" class="btn btn-sm btn-secondary" style="width: 100%; justify-content: center; margin-bottom: 8px;">
                                <i class="fas fa-qrcode"></i> View QR Code
                            </button>
                        ` : ''}
                        <button type="button" onmousedown="event.stopPropagation(); window._suppressMapClick=true;" onclick="event.stopPropagation(); showScheduleModal('${String(id).replace(/'/g, "\\'")}')" class="btn btn-sm btn-primary" style="width: 100%; justify-content: center; background: #2e7d32;">
                            <i class="fas fa-calendar-plus"></i> Schedule Spray
                        </button>
                    </div>
                </div>
            `;
            }

            // Listen for all trees in real-time
            const treesRef = ref(db, 'trees');
            onValue(treesRef, (snapshot) => {
                const treesData = snapshot.val() || {};

                Object.keys(treesData).forEach(id => {
                    if (id === '0' || id === 0) return;

                    const tree = treesData[id];
                    if (!tree.lat && !tree.latitude) return;

                    const lat = parseFloat(tree.lat || tree.latitude);
                    const lng = parseFloat(tree.lng || tree.longitude);

                    // Get next spray schedule
                    let nextSpray = null;
                    if (tree.spray_schedules) {
                        const schedules = Object.values(tree.spray_schedules);
                        const now = new Date().getTime();
                        const pending = schedules.filter(s => s.status === 'pending');
                        pending.sort((a, b) => {
                            return new Date(a.spray_date + ' ' + a.spray_time).getTime() - new Date(b.spray_date + ' ' + b.spray_time).getTime();
                        });

                        if (pending.length > 0) {
                            nextSpray = pending[0];
                        }
                    }

                    // If marker already exists, update its position and infowindow
                    if (window.treeMarkers[id]) {
                        const marker = window.treeMarkers[id];
                        const infoWindow = window.treeInfoWindows[id];

                        const nextSprayDateStr = nextSpray ? nextSpray.spray_date : null;
                        const markerStyles = getMarkerColor(marker._diseaseCount || 0, nextSprayDateStr);

                        marker.setIcon({
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 8,
                            fillOpacity: 0.9,
                            fillColor: markerStyles.color,
                            strokeColor: markerStyles.stroke,
                            strokeWeight: 2
                        });

                        // Update pulsing metadata for existing markers
                        marker._baseColor = markerStyles.color;
                        marker._pulseColor = (markerStyles.color === '#4caf50') ? '#81c784' :
                            (markerStyles.color === '#F97316') ? '#fdba74' :
                                (markerStyles.color === '#3B82F6') ? '#93c5fd' :
                                    (markerStyles.color === '#ffb300' || markerStyles.color === '#ff9800') ? '#ffcc80' :
                                        (markerStyles.color === '#f44336') ? '#ef9a9a' :
                                            markerStyles.color;
                        marker._isPulsing = true;

                        if (infoWindow) {
                            infoWindow.setContent(updateInfoWindowContent(id, { diseaseCount: marker._diseaseCount || 0 }, nextSpray));
                            if (window._allInfoWindowsVisible) {
                                infoWindow.open(window.googleMap, marker);
                            }
                        }
                    } else if (window.googleMap) {
                        // Create new marker if it doesn't exist yet
                        const nextSprayDateStr = nextSpray ? nextSpray.spray_date : null;
                        const markerStyles = getMarkerColor(0, nextSprayDateStr);

                        const marker = new google.maps.Marker({
                            position: { lat, lng },
                            map: window.googleMap,
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 8,
                                fillOpacity: 0.9,
                                fillColor: markerStyles.color,
                                strokeColor: markerStyles.stroke,
                                strokeWeight: 2
                            }
                        });

                        // Pulsing metadata
                        marker._baseColor = markerStyles.color;
                        marker._pulseColor = (markerStyles.color === '#4caf50') ? '#81c784' :
                            (markerStyles.color === '#F97316') ? '#fdba74' :
                                (markerStyles.color === '#3B82F6') ? '#93c5fd' :
                                    (markerStyles.color === '#ffb300' || markerStyles.color === '#ff9800') ? '#ffcc80' :
                                        (markerStyles.color === '#f44336') ? '#ef9a9a' :
                                            markerStyles.color;
                        marker._isPulsing = true;

                        marker._diseaseCount = 0;
                        window.treeMarkers[id] = marker;

                        const infoWindow = new google.maps.InfoWindow({
                            content: updateInfoWindowContent(id, { diseaseCount: 0 }, nextSpray)
                        });
                        window.treeInfoWindows[id] = infoWindow;

                        marker.addListener('mouseover', () => {
                            infoWindow.open(window.googleMap, marker);
                        });

                        if (window._allInfoWindowsVisible) {
                            infoWindow.open(window.googleMap, marker);
                        }
                    }
                });
            });

            /* ═══════════════════════════════════════════════════════════
               FARMER TREE SUBMISSIONS — Real-time listener
               Listens to /tree_submissions. For every node with
               status === 'pending', a distinct blue marker is placed.
               Clicking the marker opens an InfoWindow with farmer details
               and two action buttons: "Create Tree" and "Reject".
            ═══════════════════════════════════════════════════════════ */
            window.pendingMarkers = window.pendingMarkers || {};

            const submissionsRef = ref(db, 'tree_submissions');
            onValue(submissionsRef, (snap) => {
                const data = snap.val() || {};

                // Remove markers for submissions no longer pending
                Object.keys(window.pendingMarkers).forEach(sid => {
                    const sub = data[sid];
                    if (!sub || (sub.status || '').toLowerCase() !== 'pending') {
                        window.pendingMarkers[sid].setMap(null);
                        delete window.pendingMarkers[sid];
                    }
                });

                Object.keys(data).forEach(submissionId => {
                    const sub = data[submissionId];
                    if ((sub.status || '').toLowerCase() !== 'pending') return;
                    if (!sub.latitude || !sub.longitude) return;

                    // Skip if marker already rendered
                    if (window.pendingMarkers[submissionId]) return;

                    const lat = parseFloat(sub.latitude);
                    const lng = parseFloat(sub.longitude);
                    const farmerName = sub.farmerName || 'Unknown Farmer';
                    const farmerEmail = sub.farmerEmail || '';
                    const notes = sub.notes || '';
                    const submittedAt = sub.submittedAt
                        ? new Date(sub.submittedAt).toLocaleDateString('en-PH', {
                            month: 'short', day: 'numeric', year: 'numeric',
                            hour: '2-digit', minute: '2-digit'
                        })
                        : 'Unknown';

                    // Build pulsing blue pending marker
                    const pendingMarker = new google.maps.Marker({
                        position: { lat, lng },
                        map: window.googleMap,
                        zIndex: 999,
                        title: 'Pending: ' + farmerName,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 11,
                            fillOpacity: 0.92,
                            fillColor: '#2563EB',
                            strokeColor: '#1D4ED8',
                            strokeWeight: 2.5
                        }
                    });

                    // Pulsing animation metadata (reuses existing pulse system)
                    pendingMarker._baseColor = '#2563EB';
                    pendingMarker._pulseColor = '#93c5fd';
                    pendingMarker._isPulsing = true;
                    window.pendingMarkers[submissionId] = pendingMarker;

                    // Build InfoWindow content
                    const notesHtml = notes
                        ? `<div style="margin:6px 0 10px;padding:6px 8px;background:#eff6ff;border-radius:7px;font-size:11px;color:#3b5998;border-left:3px solid #2563EB;">&ldquo;${notes}&rdquo;</div>`
                        : '';

                    const safeId = submissionId.replace(/'/g, "\\'");
                    const safeName = farmerName.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    const safeEmail = farmerEmail.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    const safeDate = submittedAt.replace(/'/g, "\\'");

                    const infoContent = `
                    <div style="padding:4px;min-width:240px;font-family:'Inter',sans-serif;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #e2e8f0;">
                            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#1D4ED8);display:flex;align-items:center;justify-content:center;color:white;font-size:15px;flex-shrink:0;">⏳</div>
                            <div>
                                <div style="font-weight:700;font-size:13px;color:#1e293b;">Pending Submission</div>
                                <div style="font-size:11px;color:#64748b;">${submittedAt}</div>
                            </div>
                        </div>
                        <div style="margin-bottom:9px;">
                            <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;font-weight:700;letter-spacing:.05em;">Farmer</div>
                            <div style="font-size:13px;font-weight:700;color:#1e293b;">${farmerName}</div>
                            ${farmerEmail ? `<div style="font-size:11px;color:#64748b;">${farmerEmail}</div>` : ''}
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:10px;">
                            <div style="background:#f8fafc;border-radius:8px;padding:6px 8px;">
                                <div style="font-size:10px;color:#94a3b8;font-weight:700;">LATITUDE</div>
                                <div style="font-size:12px;font-weight:700;color:#1e293b;">${lat.toFixed(6)}</div>
                            </div>
                            <div style="background:#f8fafc;border-radius:8px;padding:6px 8px;">
                                <div style="font-size:10px;color:#94a3b8;font-weight:700;">LONGITUDE</div>
                                <div style="font-size:12px;font-weight:700;color:#1e293b;">${lng.toFixed(6)}</div>
                            </div>
                        </div>
                        ${notesHtml}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:7px;">
                            <button onclick="event.stopPropagation();showCreateTreeModal('${safeId}',${lat},${lng},'${safeName}','${safeEmail}','${safeDate}');"
                                style="background:linear-gradient(135deg,#1D4ED8,#2563EB);color:#fff;border:none;padding:9px 0;border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:4px;transition:.2s;">
                                🌱 Create Tree
                            </button>
                            <button onclick="event.stopPropagation();showRejectModal('${safeId}');"
                                style="background:#fef2f2;color:#dc2626;border:1.5px solid #fecaca;padding:9px 0;border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:4px;transition:.2s;">
                                ✕ Reject
                            </button>
                        </div>
                    </div>
                `;

                    const pendingInfoWindow = new google.maps.InfoWindow({ content: infoContent, maxWidth: 280 });

                    pendingMarker.addListener('click', () => {
                        // Close all approved-tree info windows first
                        Object.values(window.treeInfoWindows || {}).forEach(iw => iw.close());
                        pendingInfoWindow.open(window.googleMap, pendingMarker);
                    });
                });
            });

            // Set marker pulsing interval (every 5s)
            if (!window._markerPulseInterval) {
                window._markerPulseToggle = false;
                window._markerPulseInterval = setInterval(() => {
                    window._markerPulseToggle = !window._markerPulseToggle;
                    if (window.treeMarkers) {
                        Object.values(window.treeMarkers).forEach(m => {
                            if (m._isPulsing) {
                                let icon = m.getIcon();
                                icon.fillColor = window._markerPulseToggle ? m._pulseColor : m._baseColor;
                                m.setIcon(icon);
                            }
                        });
                    }
                }, 5000);
            }
        </script>
        </script>

        <!-- QR Code Modal -->
        <div id="qrModal" class="modal">
            <div class="modal-content">
                <span class="modal-close" onclick="closeQRModal()">&times;</span>
                <h2><i class="fas fa-qrcode"></i>Tree QR Code</h2>
                <div id="modalQRContent"></div>
            </div>
        </div>

        <!-- Print-only QR Code Container (hidden by default) -->
        <div id="printQRContainer" style="display: none;">
            <div class="qr-print-container">
                <img id="printQRImage" src="" alt="QR Code" style="max-width: 350px; margin: 0 auto;">
                <div class="qr-print-details">
                    <p class="qr-print-text"><strong>Tree ID:</strong> <span id="printTreeId"></span></p>
                    <p class="qr-print-text"><strong>Location:</strong> <span id="printLocation"></span></p>
                </div>
            </div>
        </div>

        <script>
            const qrModal = document.getElementById('qrModal');

            function showQRModal(treeId, location, qrUrl) {
                const modalContent = document.getElementById('modalQRContent');
                modalContent.innerHTML = `
                <div class="qr-code-container">
                    <img src="${qrUrl}" alt="QR Code" class="qr-code-image" style="max-width: 300px; margin: 20px auto; display: block;">
                    <div style="margin: 1rem 0; padding: 1rem; background: var(--light-color); border-radius: 10px; text-align: left;">
                        <p><i class="fas fa-tag" style="color: var(--secondary-color);"></i> <strong>Tree ID:</strong> ${treeId}</p>
                        <p><i class="fas fa-map-marker-alt" style="color: var(--secondary-color);"></i> <strong>Location:</strong> ${location}</p>
                    </div>
                    <div class="modal-actions">
                        <button type="button" onclick="downloadQRCode('${treeId}', '${location}', '${qrUrl}')" class="btn btn-primary">
                            <i class="fas fa-download"></i> Download
                        </button>
                        <button type="button" onclick="printQRCode('${treeId}', '${location}', '${qrUrl}')" class="btn btn-outline">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            `;
                qrModal.classList.add('show');
            }

            function closeQRModal() {
                qrModal.classList.remove('show');
            }

            // Close on outside click
            window.addEventListener('click', function (event) {
                if (event.target === qrModal) {
                    closeQRModal();
                }
            });

            function downloadQRCode(treeId, location, qrUrl) {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = function () {
                    try {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        const padding = 40;
                        const textHeight = 80;
                        const qrSize = 300;

                        canvas.width = qrSize + (padding * 2);
                        canvas.height = qrSize + (padding * 2) + textHeight;

                        ctx.fillStyle = '#FFFFFF';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(img, padding, padding, qrSize, qrSize);

                        ctx.fillStyle = '#000000';
                        ctx.font = 'bold 18px Arial';
                        ctx.textAlign = 'center';
                        ctx.fillText('Tree ID: ' + treeId, canvas.width / 2, padding + qrSize + 30);
                        ctx.fillText('Location: ' + location, canvas.width / 2, padding + qrSize + 60);

                        canvas.toBlob(function (blob) {
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = 'qr-' + treeId + '.png';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                        }, 'image/png');
                    } catch (e) {
                        const a = document.createElement('a');
                        a.href = qrUrl;
                        a.download = 'qr-' + treeId + '.png';
                        a.click();
                    }
                };
                img.src = qrUrl;
            }

            function printQRCode(treeId, location, qrUrl) {
                document.getElementById('printTreeId').textContent = treeId;
                document.getElementById('printLocation').textContent = location;
                const printImg = document.getElementById('printQRImage');
                const printContainer = document.getElementById('printQRContainer');

                printContainer.style.display = 'block';
                printImg.onload = function () {
                    setTimeout(function () {
                        window.print();
                        printContainer.style.display = 'none';
                    }, 200);
                };
                printImg.src = qrUrl;
            }
        </script>
        <!-- ═══════════════════════════════════════════════════════════
         CREATE TREE MODAL — opens when admin clicks a pending marker
         and selects "Create Tree". Pre-fills lat/lng from the submission.
    ════════════════════════════════════════════════════════════════ -->
        <div id="createTreeModal">
            <div class="ctm-modal-box">
                <!-- Header -->
                <div class="ctm-header">
                    <div class="ctm-icon"><i class="fas fa-tree"></i></div>
                    <div>
                        <p class="ctm-title">Create Official Tree</p>
                        <p class="ctm-subtitle">Complete the tree details to create an official record</p>
                    </div>
                    <button class="ctm-close" onclick="closeCreateTreeModal()">&times;</button>
                </div>

                <!-- Body -->
                <div class="ctm-body">
                    <!-- Farmer info badge -->
                    <div class="ctm-farmer-badge">
                        <div class="ctm-avatar"><i class="fas fa-user"></i></div>
                        <div class="ctm-farmer-text">
                            <strong id="ctm_farmerName">Farmer Name</strong>
                            <span id="ctm_farmerMeta">Submitted: —</span>
                        </div>
                    </div>

                    <!-- GPS Coordinates (read-only, pre-filled) -->
                    <div class="ctm-coords-row">
                        <div class="ctm-coord-box">
                            <span>📍 Latitude</span>
                            <strong id="ctm_lat_display">—</strong>
                        </div>
                        <div class="ctm-coord-box">
                            <span>📍 Longitude</span>
                            <strong id="ctm_lng_display">—</strong>
                        </div>
                    </div>

                    <form id="createTreeForm" onsubmit="submitCreateTree(event)">
                        <!-- Hidden fields -->
                        <input type="hidden" id="ctm_submissionId" name="submissionId">
                        <input type="hidden" id="ctm_lat" name="lat">
                        <input type="hidden" id="ctm_lng" name="lng">

                        <!-- Row 1: Tree ID + Tree Name -->
                        <div class="ctm-form-row">
                            <div class="ctm-form-group">
                                <label for="ctm_treeId">
                                    <i class="fas fa-tag"></i> Tree ID <span class="req">*</span>
                                </label>
                                <input type="text" id="ctm_treeId" name="treeId" class="ctm-input"
                                    placeholder="e.g. tree5" required>
                            </div>
                            <div class="ctm-form-group">
                                <label for="ctm_treeName">
                                    <i class="fas fa-leaf"></i> Tree Name
                                </label>
                                <input type="text" id="ctm_treeName" name="treeName" class="ctm-input"
                                    placeholder="e.g. Carabao Tree A">
                            </div>
                        </div>

                        <!-- Row 2: Variety + Estimated Age -->
                        <div class="ctm-form-row">
                            <div class="ctm-form-group">
                                <label for="ctm_variety">
                                    <i class="fas fa-seedling"></i> Variety
                                </label>
                                <input type="text" id="ctm_variety" name="variety" class="ctm-input"
                                    placeholder="e.g. Carabao, Apple">
                            </div>
                            <div class="ctm-form-group">
                                <label for="ctm_estimatedAge">
                                    <i class="fas fa-clock"></i> Estimated Age
                                </label>
                                <input type="text" id="ctm_estimatedAge" name="estimatedAge" class="ctm-input"
                                    placeholder="e.g. 3 years">
                            </div>
                        </div>

                        <!-- Row 3: Date Planted (full width) -->
                        <div class="ctm-form-group">
                            <label for="ctm_datePlanted">
                                <i class="fas fa-calendar-day"></i> Date Planted
                            </label>
                            <input type="date" id="ctm_datePlanted" name="datePlanted" class="ctm-input">
                        </div>

                        <!-- Row 4: Additional Info -->
                        <div class="ctm-form-group">
                            <label for="ctm_additionalInfo">
                                <i class="fas fa-sticky-note"></i> Additional Information
                            </label>
                            <textarea id="ctm_additionalInfo" name="additionalInfo" class="ctm-input"
                                placeholder="Any additional notes about this tree..."></textarea>
                        </div>

                        <!-- Alert / feedback -->
                        <div id="ctm_message" class="ctm-alert" style="display:none;"></div>

                        <!-- Actions -->
                        <div class="ctm-actions">
                            <button type="submit" id="ctm_submitBtn" class="ctm-btn-save">
                                <i class="fas fa-check-circle"></i> Create Official Tree
                            </button>
                            <button type="button" class="ctm-btn-cancel"
                                onclick="closeCreateTreeModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════
         REJECT MODAL
    ════════════════════════════════════════════════ -->
        <div id="rejectModal">
            <div class="rm-box">
                <div class="rm-header">
                    <i class="fas fa-times-circle"></i>
                    <h2>Reject Submission</h2>
                </div>
                <div class="rm-body">
                    <p>Provide an optional reason for rejection. The farmer will be notified.</p>
                    <input type="hidden" id="rm_submissionId">
                    <div class="ctm-form-group">
                        <label class="ctm-form-group"
                            style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:600;color:#2D3748;margin-bottom:.4rem;">
                            <i class="fas fa-comment-alt" style="color:#dc2626;"></i> Rejection Reason (optional)
                        </label>
                        <textarea id="rm_reason" class="ctm-input" style="width:100%;min-height:80px;"
                            placeholder="e.g. Location is outside the farm boundary."></textarea>
                    </div>
                    <div id="rm_message" class="ctm-alert" style="display:none;"></div>
                    <div class="rm-actions">
                        <button id="rm_submitBtn" class="rm-btn-reject" onclick="submitRejection()">
                            <i class="fas fa-times"></i> Confirm Rejection
                        </button>
                        <button class="rm-btn-cancel" onclick="closeRejectModal()">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Modal (Premium Redesign) -->
        <div id="scheduleModal" class="modal">
            <div class="modal-content">
                <!-- Modal Header -->
                <div class="sched-modal-header">
                    <div class="sched-modal-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <div class="sched-modal-title-box">
                        <h2>Schedule Spray Treatment</h2>
                        <p class="sched-modal-subtitle">Automate disease control or set a field maintenance plan</p>
                    </div>
                    <button type="button" class="sched-modal-close" onclick="closeScheduleModal()">&times;</button>
                </div>

                <!-- Modal Body -->
                <div class="sched-modal-body">
                    <div class="sched-tree-badge">
                        <i class="fas fa-tree"></i> Target Tree: <span id="modalTreeId">TREE-000</span>
                    </div>

                    <form id="scheduleForm" onsubmit="submitScheduleForm(event)">
                        <input type="hidden" id="sched_tree_id" name="tree_id">

                        <!-- Quick Date Presets -->
                        <div style="margin-bottom: 0.4rem; font-size: 0.78rem; font-weight: 600; color: #64748B;">Quick
                            Date Presets:</div>
                        <div class="sched-presets">
                            <button type="button" class="sched-preset-btn" onclick="setPresetDate(0, event)">⚡
                                Today</button>
                            <button type="button" class="sched-preset-btn" onclick="setPresetDate(1, event)">🌅
                                Tomorrow</button>
                            <button type="button" class="sched-preset-btn active" onclick="setPresetDate(15, event)">📅
                                +15 Days (Default)</button>
                            <button type="button" class="sched-preset-btn" onclick="setPresetDate(30, event)">🗓️ +30
                                Days</button>
                        </div>

                        <div class="sched-form-row">
                            <div class="sched-form-group">
                                <label for="spray_date"><i class="fas fa-calendar-day"></i> Spray Date *</label>
                                <input type="date" id="spray_date" class="sched-input" name="spray_date" required
                                    min="<?= date('Y-m-d') ?>" onchange="updateLiveSummary()">
                            </div>
                            <div class="sched-form-group">
                                <label for="spray_time"><i class="fas fa-clock"></i> Spray Time *</label>
                                <input type="time" id="spray_time" class="sched-input" name="spray_time" required
                                    value="<?= DEFAULT_SPRAY_TIME ?>" onchange="updateLiveSummary()">
                            </div>
                        </div>

                        <div class="sched-form-group">
                            <label for="notes"><i class="fas fa-sticky-note"></i> Treatment Notes</label>
                            <textarea id="notes" class="sched-input" name="notes"
                                placeholder="e.g. Fungicide spray for anthracnose prevention..."></textarea>
                        </div>

                        <!-- Live Summary Banner -->
                        <div class="sched-live-summary" id="schedSummaryBox">
                            <i class="fas fa-calendar-check" style="font-size: 1.1rem; color: #166534;"></i>
                            <span id="schedSummaryText">Scheduled for <strong>Today at 08:00 AM</strong></span>
                        </div>

                        <div id="scheduleMessage" style="margin-top: 15px; margin-bottom: 0; display: none;"></div>

                        <div class="sched-modal-actions">
                            <button type="submit" id="scheduleSubmitBtn" class="btn-save-sched">
                                <i class="fas fa-paper-plane"></i> Save Schedule
                            </button>
                            <button type="button" onclick="closeScheduleModal()" class="btn-cancel-sched">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const scheduleModal = document.getElementById('scheduleModal');

            function setPresetDate(days, evt) {
                const d = new Date();
                d.setDate(d.getDate() + days);
                const dateStr = d.toISOString().split('T')[0];
                const sprayDateEl = document.getElementById('spray_date');
                if (sprayDateEl) sprayDateEl.value = dateStr;

                if (evt && evt.currentTarget) {
                    document.querySelectorAll('.sched-preset-btn').forEach(btn => btn.classList.remove('active'));
                    evt.currentTarget.classList.add('active');
                }
                updateLiveSummary();
            }
            window.setPresetDate = setPresetDate;

            function selectTreatmentType(type, defaultNote, evt) {
                const notesEl = document.getElementById('notes');
                if (notesEl) {
                    notesEl.value = defaultNote;
                }
                if (evt && evt.currentTarget) {
                    document.querySelectorAll('.sched-type-pill').forEach(pill => pill.classList.remove('active'));
                    evt.currentTarget.classList.add('active');
                }
                updateLiveSummary();
            }
            window.selectTreatmentType = selectTreatmentType;

            function updateLiveSummary() {
                const dateInput = document.getElementById('spray_date');
                const timeInput = document.getElementById('spray_time');
                const summaryText = document.getElementById('schedSummaryText');
                if (!dateInput || !timeInput || !summaryText) return;

                if (!dateInput.value) {
                    summaryText.innerHTML = 'Please select a valid spray date';
                    return;
                }

                try {
                    const dateObj = new Date(dateInput.value + 'T' + (timeInput.value || '08:00'));
                    const formattedDate = dateObj.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
                    let [h, m] = (timeInput.value || '08:00').split(':');
                    h = parseInt(h);
                    const ampm = h >= 12 ? 'PM' : 'AM';
                    h = h % 12 || 12;
                    m = String(m).padStart(2, '0');
                    const formattedTime = `${h}:${m} ${ampm}`;

                    const now = new Date();
                    now.setHours(0, 0, 0, 0);
                    const targetDate = new Date(dateInput.value);
                    targetDate.setHours(0, 0, 0, 0);
                    const diffTime = targetDate - now;
                    const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

                    let relativeStr = '';
                    if (diffDays === 0) relativeStr = ' (Today)';
                    else if (diffDays === 1) relativeStr = ' (Tomorrow)';
                    else if (diffDays > 1) relativeStr = ` (${diffDays} days from now)`;
                    else if (diffDays < 0) relativeStr = ` (${Math.abs(diffDays)} days ago)`;

                    summaryText.innerHTML = `Scheduled for <strong>${formattedDate} at ${formattedTime}</strong>${relativeStr}`;
                } catch (e) {
                    summaryText.innerHTML = `Scheduled for <strong>${dateInput.value} ${timeInput.value}</strong>`;
                }
            }
            window.updateLiveSummary = updateLiveSummary;

            function showScheduleModal(treeId, location) {
                window._suppressMapClick = true;
                // Force modal to top layer FIRST so it always displays, even if a sub-step below throws
                scheduleModal.style.cssText = 'display: flex !important; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 999999 !important; background: rgba(0, 0, 0, 0.6) !important; align-items: center !important; justify-content: center !important; backdrop-filter: blur(4px) !important;';
                scheduleModal.classList.add('show');

                try {
                    if (document.getElementById('scheduleForm')) {
                        document.getElementById('scheduleForm').reset();
                        document.getElementById('spray_date').value = new Date().toISOString().split('T')[0];
                        document.getElementById('scheduleMessage').style.display = 'none';
                        document.getElementById('scheduleSubmitBtn').disabled = false;
                    }
                    document.getElementById('modalTreeId').textContent = treeId;
                    document.getElementById('sched_tree_id').value = treeId;
                    if (typeof updateLiveSummary === 'function') updateLiveSummary();
                } catch (e) {
                    console.error('showScheduleModal populate error:', e);
                }
            }

            function closeScheduleModal() {
                scheduleModal.style.cssText = 'display: none !important;';
                scheduleModal.classList.remove('show');
            }

            function submitScheduleForm(event) {
                event.preventDefault();
                const form = event.target;
                const submitBtn = document.getElementById('scheduleSubmitBtn');
                const messageDiv = document.getElementById('scheduleMessage');

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

                const formData = new FormData(form);
                fetch('save_schedule.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            messageDiv.className = 'alert alert-success';
                            messageDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;
                            messageDiv.style.display = 'block';
                            setTimeout(() => {
                                closeScheduleModal();
                            }, 1500);
                        } else {
                            messageDiv.className = 'alert alert-error';
                            messageDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}`;
                            messageDiv.style.display = 'block';
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Schedule';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        messageDiv.className = 'alert alert-error';
                        messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.';
                        messageDiv.style.display = 'block';
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Schedule';
                    });
            }

            // Add additional click listener for schedule modal
            window.addEventListener('click', function (event) {
                if (event.target === scheduleModal) {
                    closeScheduleModal();
                }
            });
        </script>

        <!-- ═══════════════════════════════════════════════════════════
         CREATE TREE MODAL JAVASCRIPT
    ════════════════════════════════════════════════════════════ -->
        <script>
            const createTreeModal = document.getElementById('createTreeModal');

            /**
             * Opens the Create Tree modal, pre-filling GPS coords and
             * farmer details from the pending submission marker click.
             */
            function showCreateTreeModal(submissionId, lat, lng, farmerName, farmerEmail, submittedDate) {
                // Reset form
                const form = document.getElementById('createTreeForm');
                if (form) form.reset();

                // Populate hidden / display fields
                document.getElementById('ctm_submissionId').value = submissionId;
                document.getElementById('ctm_lat').value = lat;
                document.getElementById('ctm_lng').value = lng;
                document.getElementById('ctm_lat_display').textContent = parseFloat(lat).toFixed(6);
                document.getElementById('ctm_lng_display').textContent = parseFloat(lng).toFixed(6);

                // Farmer badge
                document.getElementById('ctm_farmerName').textContent = farmerName || 'Unknown Farmer';
                document.getElementById('ctm_farmerMeta').textContent =
                    (farmerEmail ? farmerEmail + ' · ' : '') + 'Submitted: ' + submittedDate;

                // Reset state
                const msgEl = document.getElementById('ctm_message');
                if (msgEl) msgEl.style.display = 'none';
                const btn = document.getElementById('ctm_submitBtn');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle"></i> Create Official Tree'; }

                createTreeModal.classList.add('active');
            }
            window.showCreateTreeModal = showCreateTreeModal;

            function closeCreateTreeModal() {
                createTreeModal.classList.remove('active');
            }
            window.closeCreateTreeModal = closeCreateTreeModal;

            /**
             * Submits the Create Tree form to approve_submission.php.
             * On success: removes the pending marker, closes modal.
             */
            async function submitCreateTree(event) {
                event.preventDefault();

                const submissionId = document.getElementById('ctm_submissionId').value;
                const treeId = document.getElementById('ctm_treeId').value.trim();
                const treeName = document.getElementById('ctm_treeName').value.trim();
                const variety = document.getElementById('ctm_variety').value.trim();
                const estimatedAge = document.getElementById('ctm_estimatedAge').value.trim();
                const datePlanted = document.getElementById('ctm_datePlanted').value;
                const additionalInfo = document.getElementById('ctm_additionalInfo').value.trim();

                const msgEl = document.getElementById('ctm_message');
                const btn = document.getElementById('ctm_submitBtn');

                // Client-side validation
                if (!treeId) {
                    msgEl.className = 'ctm-alert ctm-alert-error';
                    msgEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Tree ID is required.';
                    msgEl.style.display = 'flex';
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
                msgEl.style.display = 'none';

                try {
                    const res = await fetch('approve_submission.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            submissionId, treeId, treeName, variety,
                            estimatedAge, datePlanted, additionalInfo
                        })
                    });
                    const data = await res.json();

                    if (data.success) {
                        msgEl.className = 'ctm-alert ctm-alert-success';
                        msgEl.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                        msgEl.style.display = 'flex';
                        btn.innerHTML = '<i class="fas fa-check"></i> Done!';

                        // Remove the pending blue marker immediately
                        if (window.pendingMarkers && window.pendingMarkers[submissionId]) {
                            window.pendingMarkers[submissionId].setMap(null);
                            delete window.pendingMarkers[submissionId];
                        }

                        // The /trees Firebase listener will automatically add the new approved marker
                        setTimeout(() => closeCreateTreeModal(), 2200);
                    } else {
                        msgEl.className = 'ctm-alert ctm-alert-error';
                        msgEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                        msgEl.style.display = 'flex';
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check-circle"></i> Create Official Tree';
                    }
                } catch (e) {
                    msgEl.className = 'ctm-alert ctm-alert-error';
                    msgEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
                    msgEl.style.display = 'flex';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Create Official Tree';
                }
            }
            window.submitCreateTree = submitCreateTree;

            /* ── REJECT MODAL ─────────────────────────────────────────── */
            const rejectModal = document.getElementById('rejectModal');

            function showRejectModal(submissionId) {
                document.getElementById('rm_submissionId').value = submissionId;
                const ta = document.getElementById('rm_reason');
                if (ta) ta.value = '';
                const msgEl = document.getElementById('rm_message');
                if (msgEl) msgEl.style.display = 'none';
                const btn = document.getElementById('rm_submitBtn');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-times"></i> Confirm Rejection'; }
                rejectModal.classList.add('active');
            }
            window.showRejectModal = showRejectModal;

            function closeRejectModal() {
                rejectModal.classList.remove('active');
            }
            window.closeRejectModal = closeRejectModal;

            async function submitRejection() {
                const submissionId = document.getElementById('rm_submissionId').value;
                const reason = document.getElementById('rm_reason').value.trim();
                const msgEl = document.getElementById('rm_message');
                const btn = document.getElementById('rm_submitBtn');

                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rejecting...';
                if (msgEl) msgEl.style.display = 'none';

                try {
                    const res = await fetch('reject_submission.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ submissionId, reason })
                    });
                    const data = await res.json();

                    if (data.success) {
                        // The Firebase onValue listener will automatically remove the blue marker
                        // when it detects status !== 'pending'
                        if (window.pendingMarkers && window.pendingMarkers[submissionId]) {
                            window.pendingMarkers[submissionId].setMap(null);
                            delete window.pendingMarkers[submissionId];
                        }
                        closeRejectModal();
                    } else {
                        if (msgEl) {
                            msgEl.className = 'ctm-alert ctm-alert-error';
                            msgEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                            msgEl.style.display = 'flex';
                        }
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-times"></i> Confirm Rejection';
                    }
                } catch (e) {
                    if (msgEl) {
                        msgEl.className = 'ctm-alert ctm-alert-error';
                        msgEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error.';
                        msgEl.style.display = 'flex';
                    }
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-times"></i> Confirm Rejection';
                }
            }
            window.submitRejection = submitRejection;

            // Close modals on backdrop click
            window.addEventListener('click', (e) => {
                if (e.target === createTreeModal) closeCreateTreeModal();
                if (e.target === rejectModal) closeRejectModal();
            });
        </script>
</body>

</html>