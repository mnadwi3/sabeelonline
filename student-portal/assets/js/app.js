/**
 * Student Portal UI + marksheet PDF/image download
 */
(function () {
  'use strict';

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var existing = document.querySelector('script[src="' + src + '"]');
      if (existing) {
        existing.addEventListener('load', function () { resolve(); });
        if (existing.dataset.loaded === '1') resolve();
        setTimeout(resolve, 800);
        return;
      }
      var s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.onload = function () { s.dataset.loaded = '1'; resolve(); };
      s.onerror = function () { reject(new Error('Failed to load ' + src)); };
      document.head.appendChild(s);
    });
  }

  function ensureLibs() {
    var tasks = [];
    if (typeof html2canvas !== 'function') {
      tasks.push(loadScript('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js'));
    }
    if (!(window.jspdf && window.jspdf.jsPDF) && typeof window.jsPDF !== 'function') {
      tasks.push(loadScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js'));
    }
    return Promise.all(tasks);
  }

  function getJsPdf() {
    if (window.jspdf && window.jspdf.jsPDF) return window.jspdf.jsPDF;
    if (typeof window.jsPDF === 'function') return window.jsPDF;
    return null;
  }

  function buildFilename(sheet, ext) {
    var rows = sheet.querySelectorAll('.ms-value');
    var nameText = rows.length > 1 ? rows[1].textContent : (rows[0] ? rows[0].textContent : 'result');
    var safeName = String(nameText || 'result')
      .trim()
      .replace(/[^\w\-]+/g, '_')
      .slice(0, 40);
    return 'Marksheet_' + (safeName || 'result') + (ext || '.pdf');
  }

  function waitForImages(root) {
    var imgs = Array.prototype.slice.call(root.querySelectorAll('img'));
    return Promise.all(imgs.map(function (img) {
      if (img.complete && img.naturalWidth > 0) return Promise.resolve();
      return new Promise(function (resolve) {
        var done = function () { resolve(); };
        img.addEventListener('load', done, { once: true });
        img.addEventListener('error', done, { once: true });
        setTimeout(done, 4000);
      });
    }));
  }

  function delay(ms) {
    return new Promise(function (r) { setTimeout(r, ms); });
  }

  function measureOuterBox(root) {
    var rootRect = root.getBoundingClientRect();
    var top = rootRect.top;
    var left = rootRect.left;
    var bottom = rootRect.bottom;
    var right = rootRect.right;
    var nodes = root.querySelectorAll('*');
    for (var i = 0; i < nodes.length; i++) {
      var r = nodes[i].getBoundingClientRect();
      if (!r.width && !r.height) continue;
      if (r.top < top) top = r.top;
      if (r.left < left) left = r.left;
      if (r.bottom > bottom) bottom = r.bottom;
      if (r.right > right) right = r.right;
    }
    return {
      width: Math.ceil(Math.max(root.offsetWidth || 0, root.scrollWidth || 0, right - left)),
      height: Math.ceil(Math.max(root.offsetHeight || 0, root.scrollHeight || 0, bottom - top)),
    };
  }

  /** Find non-white content bounds on canvas (for placing the drawn border). */
  function findInkBounds(canvas) {
    var ctx = canvas.getContext('2d');
    var w = canvas.width;
    var h = canvas.height;
    var data = ctx.getImageData(0, 0, w, h).data;
    var minX = w;
    var minY = h;
    var maxX = 0;
    var maxY = 0;
    var found = false;
    for (var y = 0; y < h; y++) {
      for (var x = 0; x < w; x++) {
        var i = (y * w + x) * 4;
        if (data[i] < 248 || data[i + 1] < 248 || data[i + 2] < 248) {
          found = true;
          if (x < minX) minX = x;
          if (y < minY) minY = y;
          if (x > maxX) maxX = x;
          if (y > maxY) maxY = y;
        }
      }
    }
    if (!found) {
      return { minX: 0, minY: 0, maxX: w - 1, maxY: h - 1 };
    }
    return { minX: minX, minY: minY, maxX: maxX, maxY: maxY };
  }

  /**
   * html2canvas often clips the CSS bottom border. We capture WITHOUT a CSS
   * border, then paint a full blue rectangle onto the bitmap so all 4 sides exist.
   * INNER pad keeps Student ID / labels off the border line (screen has CSS padding;
   * tight ink-crop was sticking text to the line in downloads).
   */
  function paintFullBorder(sourceCanvas) {
    var SCALE = 2;
    var OUTER = 10 * SCALE; /* white outside the border */
    var INNER = 22 * SCALE; /* space inside border → content (fixes stuck labels) */
    var LINE = 2 * SCALE;
    var ink = findInkBounds(sourceCanvas);

    var contentW = ink.maxX - ink.minX + 1;
    var contentH = ink.maxY - ink.minY + 1;
    var boxW = contentW + INNER * 2;
    var boxH = contentH + INNER * 2;
    var outW = boxW + OUTER * 2 + LINE * 2;
    var outH = boxH + OUTER * 2 + LINE * 2;

    var out = document.createElement('canvas');
    out.width = outW;
    out.height = outH;
    var ctx = out.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, outW, outH);

    var drawX = OUTER + LINE + INNER;
    var drawY = OUTER + LINE + INNER;
    ctx.drawImage(
      sourceCanvas,
      ink.minX, ink.minY, contentW, contentH,
      drawX, drawY, contentW, contentH
    );

    ctx.strokeStyle = '#1e3a6e';
    ctx.lineWidth = LINE;
    ctx.lineJoin = 'miter';
    ctx.strokeRect(
      OUTER + LINE / 2,
      OUTER + LINE / 2,
      boxW + LINE,
      boxH + LINE
    );

    return out;
  }

  async function captureMarksheet(sheet) {
    var EXPORT_W = 794;
    var FRAME_PAD = 20;

    var host = document.createElement('div');
    host.setAttribute('aria-hidden', 'true');
    host.style.cssText = [
      'position:fixed',
      'left:-12000px',
      'top:0',
      'background:#ffffff',
      'z-index:-1',
      'pointer-events:none',
      'overflow:visible',
    ].join(';');

    var frame = document.createElement('div');
    frame.className = 'marksheet-export-frame';
    frame.style.cssText = [
      'display:block',
      'box-sizing:border-box',
      'width:' + (EXPORT_W + FRAME_PAD * 2) + 'px',
      'padding:' + FRAME_PAD + 'px',
      'background:#ffffff',
      'overflow:visible',
    ].join(';');

    var clone = sheet.cloneNode(true);
    clone.removeAttribute('id');
    clone.classList.add('marksheet-exporting');
    /* No CSS border here — border is painted after capture (avoids clip bug) */
    clone.style.cssText = [
      'box-sizing:border-box',
      'width:' + EXPORT_W + 'px',
      'max-width:' + EXPORT_W + 'px',
      'margin:0',
      'box-shadow:none',
      'transform:none',
      'overflow:visible',
      'background:#ffffff',
      'position:relative',
      'border:none',
      'outline:none',
      'display:block',
    ].join(';');

    var inner = clone.querySelector('.marksheet-inner');
    if (inner) {
      inner.style.overflow = 'visible';
      inner.style.padding = '1.35rem 1.6rem 2.25rem';
      inner.style.boxSizing = 'border-box';
    }

    var meta = clone.querySelector('.ms-meta');
    if (meta) {
      meta.style.paddingLeft = '0.35rem';
      meta.style.paddingRight = '0.35rem';
    }

    var legend = clone.querySelector('.grade-legend');
    if (legend) {
      legend.style.marginBottom = '0.5rem';
      legend.style.paddingBottom = '0.55rem';
    }

    var principal = clone.querySelector('img.sign-principal');
    if (principal) {
      principal.style.transform = 'rotate(-8deg)';
      principal.style.transformOrigin = 'center bottom';
      principal.style.mixBlendMode = 'normal';
      principal.style.filter = 'none';
    }

    frame.appendChild(clone);
    host.appendChild(frame);
    document.body.appendChild(host);

    try {
      await waitForImages(clone);
      await delay(180);

      var sheetSize = measureOuterBox(clone);
      clone.style.minHeight = (sheetSize.height + 12) + 'px';
      await delay(80);

      var frameSize = measureOuterBox(frame);
      var captureW = Math.ceil(frameSize.width + 6);
      var captureH = Math.ceil(frameSize.height + 16);
      host.style.width = captureW + 'px';
      frame.style.width = captureW + 'px';

      var raw = await html2canvas(frame, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false,
        imageTimeout: 8000,
        scrollX: 0,
        scrollY: 0,
        x: 0,
        y: 0,
        width: captureW,
        height: captureH,
        windowWidth: captureW + 60,
        windowHeight: captureH + 60,
        onclone: function (doc, el) {
          el.style.background = '#ffffff';
          el.style.overflow = 'visible';
          el.style.padding = FRAME_PAD + 'px';
          el.style.paddingBottom = (FRAME_PAD + 14) + 'px';
          var ms = el.querySelector('.marksheet-exporting');
          if (ms) {
            ms.style.border = 'none';
            ms.style.outline = 'none';
            ms.style.boxShadow = 'none';
            ms.style.overflow = 'visible';
          }
          var imgs = el.querySelectorAll('img');
          for (var i = 0; i < imgs.length; i++) {
            imgs[i].style.mixBlendMode = 'normal';
            imgs[i].style.filter = 'none';
          }
        },
      });

      return paintFullBorder(raw);
    } finally {
      host.remove();
    }
  }

  /** PDF page = full captured content (includes signatures + grade legend). */
  async function downloadMarksheetPdf(btn) {
    var sheet = document.getElementById('marksheet');
    if (!sheet) {
      alert('Marksheet not found on this page.');
      return;
    }

    var prevText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Preparing…';

    try {
      await ensureLibs();
      var JsPDF = getJsPdf();
      if (typeof html2canvas !== 'function' || !JsPDF) {
        throw new Error('PDF libraries unavailable');
      }

      var canvas = await captureMarksheet(sheet);
      /* PNG keeps the painted border sharp (JPEG can soften the bottom edge) */
      var imgData = canvas.toDataURL('image/png');

      var PX_TO_MM = 25.4 / 96;
      var contentW = (canvas.width / 2) * PX_TO_MM;
      var contentH = (canvas.height / 2) * PX_TO_MM;
      var pad = 3;
      var pageW = Math.max(contentW + pad * 2, 40);
      var pageH = Math.max(contentH + pad * 2, 40);

      var pdf = new JsPDF({
        orientation: pageW >= pageH ? 'l' : 'p',
        unit: 'mm',
        format: [pageW, pageH],
        compress: true,
      });

      pdf.setFillColor(255, 255, 255);
      pdf.rect(0, 0, pageW, pageH, 'F');
      pdf.addImage(imgData, 'PNG', pad, pad, contentW, contentH);
      pdf.save(buildFilename(sheet, '.pdf'));
    } catch (err) {
      console.error(err);
      alert('PDF download failed. Try “Download Image” for a clean printable file.');
    } finally {
      btn.disabled = false;
      btn.textContent = prevText;
    }
  }

  /** Image download — full marksheet including footer */
  async function downloadMarksheetImage(btn) {
    var sheet = document.getElementById('marksheet');
    if (!sheet) {
      alert('Marksheet not found on this page.');
      return;
    }

    var prevText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Preparing…';

    try {
      await ensureLibs();
      if (typeof html2canvas !== 'function') throw new Error('html2canvas missing');

      var canvas = await captureMarksheet(sheet);
      var a = document.createElement('a');
      a.href = canvas.toDataURL('image/png');
      a.download = buildFilename(sheet, '.png');
      document.body.appendChild(a);
      a.click();
      a.remove();
    } catch (err) {
      console.error(err);
      alert('Image download failed. Please try again.');
    } finally {
      btn.disabled = false;
      btn.textContent = prevText;
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (!confirm(el.getAttribute('data-confirm') || 'Are you sure?')) {
          e.preventDefault();
        }
      });
    });

    var downloadBtn = document.getElementById('btnDownloadPdf');
    if (downloadBtn) {
      downloadBtn.addEventListener('click', function () {
        downloadMarksheetPdf(downloadBtn);
      });
    }

    var imgBtn = document.getElementById('btnDownloadImage');
    if (imgBtn) {
      imgBtn.addEventListener('click', function () {
        downloadMarksheetImage(imgBtn);
      });
    }

    var form = document.getElementById('marks-entry-form');
    if (form) {
      var recalc = function () {
        var obtained = 0;
        var maximum = 0;
        form.querySelectorAll('.mark-row').forEach(function (row) {
          var max = parseFloat((row.querySelector('.max-marks') || {}).value || '0');
          var obt = parseFloat((row.querySelector('.obt-marks') || {}).value || '0');
          maximum += max;
          obtained += obt;
        });
        var pct = maximum > 0 ? (obtained / maximum) * 100 : 0;
        var p = Math.round(pct * 100) / 100;
        var passPct = 40;
        var grade = 'F';
        var thresholds = [
          [90, 'A1'], [80, 'A2'], [70, 'B1'],
          [60, 'B2'], [50, 'C1'], [40, 'C2'],
        ];
        for (var i = 0; i < thresholds.length; i++) {
          if (p >= thresholds[i][0]) {
            grade = thresholds[i][1];
            break;
          }
        }
        if (p < passPct) grade = 'F';
        var status = p >= passPct ? 'Pass' : 'Fail';
        var set = function (id, val) {
          var n = document.getElementById(id);
          if (n) n.textContent = val;
        };
        set('live-total', Math.round(obtained) + ' / ' + Math.round(maximum));
        set('live-pct', (Math.abs(p - Math.round(p)) < 0.001 ? String(Math.round(p)) : p.toFixed(1)) + '%');
        set('live-grade', grade);
        set('live-status', status);
      };
      form.addEventListener('input', recalc);
      recalc();
    }
  });
})();
