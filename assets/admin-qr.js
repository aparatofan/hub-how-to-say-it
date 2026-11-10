/* HUB admin: draws the QR code for a chapter link and offers it as PNG or SVG. */
(function () {
  'use strict';
  var box = document.getElementById('htsi-qr');
  if (!box || typeof qrcode !== 'function') { return; }
  var link = box.getAttribute('data-link');
  var name = box.getAttribute('data-name') || 'qr';
  var QUIET = 4; // white border, in modules (the QR standard asks for four)

  var qr = qrcode(0, 'M');
  qr.addData(link);
  qr.make();
  var n = qr.getModuleCount();
  var total = n + QUIET * 2;

  function draw(canvas, px) {
    // Whole pixels per module keep the edges sharp.
    var cell = Math.max(1, Math.floor(px / total));
    var size = cell * total;
    canvas.width = size;
    canvas.height = size;
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, size, size);
    ctx.fillStyle = '#000';
    for (var r = 0; r < n; r++) {
      for (var c = 0; c < n; c++) {
        if (qr.isDark(r, c)) { ctx.fillRect((c + QUIET) * cell, (r + QUIET) * cell, cell, cell); }
      }
    }
    return canvas;
  }

  function svg() {
    var d = '';
    for (var r = 0; r < n; r++) {
      for (var c = 0; c < n; c++) {
        if (qr.isDark(r, c)) { d += 'M' + (c + QUIET) + ' ' + (r + QUIET) + 'h1v1h-1z'; }
      }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + total + ' ' + total + '" width="1000" height="1000" shape-rendering="crispEdges">' +
      '<rect width="' + total + '" height="' + total + '" fill="#fff"/><path d="' + d + '" fill="#000"/></svg>';
  }

  function save(href, file) {
    var a = document.createElement('a');
    a.href = href;
    a.download = file;
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  var preview = draw(document.createElement('canvas'), 520);
  preview.style.cssText = 'width:180px;height:180px;border:1px solid #c3c4c7;background:#fff;display:block';
  preview.setAttribute('role', 'img');
  preview.setAttribute('aria-label', 'QR code for ' + link);
  box.insertBefore(preview, box.firstChild);

  box.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t.getAttribute) { return; }
    var kind = t.getAttribute('data-qr');
    if (kind === 'png') {
      save(draw(document.createElement('canvas'), 1000).toDataURL('image/png'), name + '.png');
    } else if (kind === 'svg') {
      var url = URL.createObjectURL(new Blob([svg()], { type: 'image/svg+xml' }));
      save(url, name + '.svg');
      setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }
  });
})();
