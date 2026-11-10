/* HUB — How to Say It in English. No dependencies. */
(function () {
  'use strict';

  var C = window.HTSI_CONFIG || {};
  var app = document.getElementById('htsi-app');
  if (!app) { return; }

  var S = {
    loaded: false,
    loadError: '',
    reader: null,
    volumes: [],
    vol: 1,
    chapters: {},     // "v-c" -> chapter data, or {error: '...'}
    work: {},         // "v-c-i" -> answers for one exercise (kept only while the page is open)
    mapSent: {},      // "v-c" -> email address, or {error}
    open: {},         // "v-c" -> which chapter sections are open (all closed at first)
    busy: false
  };

  /* ---------- helpers ---------- */

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (ch) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
  }

  // **text** becomes a highlighted expression; "A: " at the start becomes a speaker label.
  function rich(s) {
    var out = esc(s).replace(/\*\*(.+?)\*\*/g, '<strong class="htsi-hl">$1</strong>');
    return out.replace(/^([A-Z]):\s/, '<span class="htsi-sp">$1:</span> ');
  }

  // Key notes: _text_ becomes italics (for a quoted word or phrase).
  function note(s) {
    return esc(s).replace(/(^|[\s(])_(.+?)_(?=[\s.,;:!?)]|$)/g, '$1<em>$2</em>');
  }

  // The first **highlighted** expression in a list of dialogue lines.
  function bold(lines) {
    for (var i = 0; i < (lines || []).length; i++) {
      var m = /\*\*(.+?)\*\*/.exec(String(lines[i]));
      if (m) { return m[1]; }
    }
    return '';
  }

  function safeUrl(u) {
    return /^https?:\/\//i.test(String(u || '')) ? String(u) : '';
  }

  function api(method, path, body) {
    var opt = { method: method, credentials: 'same-origin', headers: { 'X-HTSI': '1' } };
    if (body) {
      opt.headers['Content-Type'] = 'application/json';
      opt.body = JSON.stringify(body);
    }
    return fetch(C.api + path, opt).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) {
          var err = new Error(data && data.message ? data.message : 'Something went wrong. Please try again.');
          err.status = res.status;
          throw err;
        }
        return data;
      });
    }, function () {
      throw new Error('No connection. Check your internet and try again.');
    });
  }

  var ICON = {
    right: '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>',
    left: '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>',
    out: '<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M9 7h8v8"/></svg>',
    map: '<svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#102564" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 9V4M12 20v-5M9.4 13.5L5 16M14.6 10.5L19 8M9.4 10.5L5 8M14.6 13.5L19 16"/></svg>',
    screen: '<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>',
    lock: '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>',
    down: '<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>',
    key: '<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M11 12l8-8M16 7l3 3M14 9l2 2"/></svg>'
  };

  /* ---------- routing ---------- */

  // #/            home
  // #/1/1         chapter
  // #/1/1/ex/0    exercise
  // #/1/1/key/0   key to one exercise
  function route() {
    var p = (location.hash || '').replace(/^#\/?/, '').split('/');
    var v = parseInt(p[0], 10), c = parseInt(p[1], 10);
    if (!(v >= 1 && v <= 3 && c >= 1 && c <= 10)) { return { name: 'home' }; }
    if (p[2] === 'ex' && /^\d+$/.test(p[3] || '')) { return { name: 'ex', v: v, c: c, i: parseInt(p[3], 10) }; }
    if (p[2] === 'key') { return { name: 'key', v: v, c: c, i: /^\d+$/.test(p[3] || '') ? parseInt(p[3], 10) : 0 }; }
    return { name: 'chapter', v: v, c: c };
  }

  function go(hash) {
    if (location.hash === hash) { render(); } else { location.hash = hash; }
  }

  function volume(n) {
    for (var i = 0; i < S.volumes.length; i++) { if (S.volumes[i].n === n) { return S.volumes[i]; } }
    return null;
  }

  function outline(v, c) {
    var vol = volume(v);
    return vol && vol.chapters[c - 1] ? vol.chapters[c - 1] : null;
  }

  function unlocked(v) {
    return !!(S.reader && S.reader.volumes.indexOf(v) !== -1);
  }

  function applyState(data) {
    S.reader = data.reader || null;
    S.volumes = data.volumes || [];
    if (!S.reader) { S.chapters = {}; S.work = {}; S.mapSent = {}; S.open = {}; }
  }

  function loadChapter(v, c) {
    var k = v + '-' + c;
    if (S.chapters[k]) { return; }
    S.chapters[k] = { loading: true };
    api('GET', '/chapter?v=' + v + '&c=' + c).then(function (data) {
      S.chapters[k] = data;
      render();
    }, function (err) {
      if (err.status === 401) { S.reader = null; S.chapters = {}; render(); return; }
      S.chapters[k] = { error: err.message };
      render();
    });
  }

  /* ---------- screens ---------- */

  function viewLoading(text) {
    return '<div class="htsi-loading" role="status">' + esc(text || 'Loading…') + '</div>';
  }

  function viewLogin(r) {
    var chip = '';
    if (r.v) {
      var o = outline(r.v, r.c);
      if (o) { chip = '<div class="htsi-chip">Volume ' + r.v + ' · Chapter ' + r.c + ' · ' + esc(o.title) + '</div>'; }
    }
    var privacy = safeUrl(C.privacy) ? ' <a href="' + esc(C.privacy) + '" target="_blank" rel="noopener">Privacy policy</a>' : '';
    return '<div class="htsi-screen htsi-login">' +
      '<div class="htsi-login-top">' +
        '<div class="htsi-brand"><img src="' + esc(C.logoWhite) + '" alt="The Blue Tree" width="76" height="66"><div class="htsi-eyebrow">The Blue Tree</div></div>' +
        '<h1>How to Say It in English</h1>' +
        '<div class="htsi-rule"></div>' +
        '<p>Your mind maps, games and exercises in one place.</p>' +
      '</div>' +
      '<form class="htsi-sheet" data-form="login" novalidate>' +
        chip +
        '<h2>Open your HUB</h2>' +
        '<div class="htsi-field"><label for="htsi-code">Code from your book</label>' +
          '<input class="htsi-input htsi-code" id="htsi-code" name="code" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="HTS1-XXXX-XXXX" required></div>' +
        '<div class="htsi-field"><label for="htsi-email">Email</label>' +
          '<input class="htsi-input" id="htsi-email" name="email" type="email" autocomplete="email" inputmode="email" placeholder="you@example.com" required></div>' +
        '<div class="htsi-field"><label for="htsi-name">First name (you can leave this empty)</label>' +
          '<input class="htsi-input" id="htsi-name" name="name" type="text" autocomplete="given-name" maxlength="60"></div>' +
        '<label class="htsi-check"><input type="checkbox" name="news"> <span>Send me news about the book by email. You can stop at any time.</span></label>' +
        '<div class="htsi-error" role="alert" hidden></div>' +
        '<button class="htsi-btn" type="submit">Open the HUB</button>' +
        '<p class="htsi-small htsi-muted">Your code works with one email address only. Next time, sign in with the same code and email. You can use the code for as long as you like.' + privacy + '</p>' +
      '</form>' +
    '</div>';
  }

  function chapterStatus(o) {
    var bits = [];
    if (o.map) { bits.push('Mind map'); }
    if (o.games) { bits.push(o.games + (o.games === 1 ? ' game' : ' games')); }
    if (o.exercises) { bits.push('Exercises'); }
    return bits.join(' · ');
  }

  function viewHome() {
    var vol = volume(S.vol) || S.volumes[0];
    if (!vol) { return viewLoading('Nothing here yet.'); }
    var open = unlocked(vol.n);
    var name = S.reader && S.reader.name ? ', ' + esc(S.reader.name) : '';

    var tabs = S.volumes.map(function (v) {
      var lock = unlocked(v.n) ? '' : ICON.lock;
      return '<button type="button" class="htsi-vol" data-act="vol" data-n="' + v.n + '" aria-pressed="' + (v.n === vol.n) + '" aria-label="Volume ' + v.n + (unlocked(v.n) ? '' : ', locked') + '">' +
        '<span class="htsi-vol-label">VOLUME ' + lock + '</span><span class="htsi-vol-n">' + v.n + '</span></button>';
    }).join('');

    var rows = vol.chapters.map(function (o) {
      var num = (o.n < 10 ? '0' : '') + o.n;
      var status = chapterStatus(o);
      if (open && status) {
        return '<a class="htsi-row" href="#/' + vol.n + '/' + o.n + '"><span class="htsi-row-num">' + num + '</span>' +
          '<span class="htsi-row-main"><span class="htsi-row-title">' + esc(o.title) + '</span><span class="htsi-row-sub">' + esc(status) + '</span></span>' +
          '<span style="color:var(--blue)">' + ICON.right + '</span></a>';
      }
      return '<div class="htsi-row is-off"><span class="htsi-row-num">' + num + '</span>' +
        '<span class="htsi-row-main"><span class="htsi-row-title">' + esc(o.title) + '</span>' +
        (open ? '<span class="htsi-row-sub">Coming soon</span>' : '') + '</span></div>';
    }).join('');

    var unlock = '';
    if (!open) {
      var shop = safeUrl(C.shop && C.shop[vol.n]);
      unlock = '<form class="htsi-panel" data-form="unlock" novalidate>' +
        '<div class="htsi-panel-head"><span style="color:var(--blue)">' + ICON.lock + '</span><h2>Open Volume ' + vol.n + '</h2></div>' +
        '<p class="htsi-muted" style="font-size:14px">Volume ' + vol.n + ' has ten more mind maps. Type the code from your Volume ' + vol.n + ' book to open them.</p>' +
        '<div class="htsi-field"><label for="htsi-ucode">Volume ' + vol.n + ' code</label>' +
          '<input class="htsi-input htsi-code" id="htsi-ucode" name="code" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="HTS' + vol.n + '-XXXX-XXXX" required></div>' +
        '<div class="htsi-error" role="alert" hidden></div>' +
        '<button class="htsi-btn" type="submit">Open Volume ' + vol.n + '</button>' +
        (shop ? '<p class="htsi-small htsi-muted">No book yet? <a href="' + esc(shop) + '" target="_blank" rel="noopener">See Volume ' + vol.n + '</a></p>' : '') +
      '</form>';
    }

    return '<div class="htsi-screen htsi-home">' +
      '<img class="htsi-home-logo" src="' + esc(C.logoColour) + '" alt="The Blue Tree" width="150" height="130">' +
      '<div class="htsi-home-head">' +
        '<div class="htsi-eyebrow">How to Say It in English</div>' +
        '<h1>Welcome' + name + '.</h1>' +
        '<div class="htsi-rule"></div>' +
        '<p>Choose a volume, then a chapter.</p>' +
      '</div>' +
      '<div class="htsi-vols">' + tabs + '</div>' +
      '<div class="htsi-volname"><h2>' + esc(vol.name) + '</h2><span class="htsi-small htsi-muted">10 chapters</span></div>' +
      unlock +
      '<div class="htsi-list">' + rows + '</div>' +
      '<div class="htsi-foot">' +
        '<label class="htsi-check"><input type="checkbox" data-act="news"' + (S.reader.news ? ' checked' : '') + '> <span>Send me news about the book by email.</span></label>' +
        '<p class="htsi-small htsi-muted">Signed in as ' + esc(S.reader.email) + '</p>' +
        '<button type="button" class="htsi-link" data-act="logout">Sign out</button>' +
      '</div>' +
    '</div>';
  }

  function hero(r, title, backHref, backLabel) {
    return '<div class="htsi-hero">' +
      '<img class="htsi-hero-logo" src="' + esc(C.logoWhite) + '" alt="" width="150" height="130">' +
      '<a class="htsi-back" href="' + backHref + '">' + ICON.left + esc(backLabel) + '</a>' +
      '<div class="htsi-eyebrow">Volume ' + r.v + ' · Chapter ' + r.c + '</div>' +
      '<h1>' + esc(title) + '</h1><div class="htsi-rule"></div></div>';
  }

  function section(k, n, id, title, sub, inner) {
    var open = !!(S.open[k] && S.open[k][id]);
    return '<div class="htsi-acc' + (open ? ' is-open' : '') + '">' +
      '<button type="button" class="htsi-acc-head" data-act="toggle" data-s="' + id + '" aria-expanded="' + open + '" aria-controls="htsi-acc-' + id + '">' +
        '<span class="htsi-acc-n">' + n + '</span>' +
        '<span class="htsi-acc-text"><span class="htsi-acc-title">' + esc(title) + '</span><span class="htsi-acc-sub">' + esc(sub) + '</span></span>' +
        '<span class="htsi-acc-chev">' + ICON.down + '</span></button>' +
      (open ? '<div class="htsi-acc-panel" id="htsi-acc-' + id + '">' + inner + '</div>' : '') +
    '</div>';
  }

  function viewChapter(r, ch) {
    var k = r.v + '-' + r.c;
    var base = '#/' + r.v + '/' + r.c;
    var hasEx = !!(ch.exercises && ch.exercises.length);
    var inner;

    // 1. mind map
    var map = safeUrl(ch.map_url);
    if (map) {
      var sent = S.mapSent[k];
      var send;
      if (sent && sent.to) {
        send = '<div class="htsi-ok" role="status">Sent to ' + esc(sent.to) + '</div>';
      } else {
        send = (sent && sent.error ? '<div class="htsi-error" role="alert">' + esc(sent.error) + '</div>' : '') +
          '<button type="button" class="htsi-btn-onnavy" data-act="sendmap"' + (S.busy ? ' disabled' : '') + '>Email me the link</button>';
      }
      inner = '<div class="htsi-map">' +
        '<a class="htsi-map-open" href="' + esc(map) + '" target="_blank" rel="noopener"><span class="htsi-map-icon">' + ICON.map + '</span>' +
          '<span style="flex:1"><span class="htsi-map-title">Open the mind map</span><span class="htsi-map-sub">It opens in a new tab</span></span>' + ICON.out + '</a>' +
        '<div class="htsi-map-line"></div>' +
        '<div class="htsi-map-note">' + ICON.screen + '<p>The map is big. It works best on a computer or a tablet. Send the link to your email and open it there.</p></div>' +
        send +
      '</div>';
    } else {
      inner = '<div class="htsi-empty">The mind map for this chapter is not ready yet. Please come back soon.</div>';
    }
    var html = section(k, 1, 'map', 'Access the mind map', 'All the phrases, with audio and examples', inner);

    // 2. Fill games
    if (ch.branches && ch.branches.length) {
      inner = '<div class="htsi-list">' + ch.branches.map(function (b, i) {
        var url = safeUrl(b.fill_url);
        var row = '<span class="htsi-dot">' + (i + 1) + '</span><span class="htsi-row-main"><span class="htsi-row-title">' + esc(b.title) + '</span></span>';
        return url
          ? '<a class="htsi-row" href="' + esc(url) + '" target="_blank" rel="noopener">' + row + '<span class="htsi-row-end">Play</span></a>'
          : '<div class="htsi-row is-off">' + row + '<span class="htsi-row-end">Soon</span></div>';
      }).join('') + '</div>';
    } else {
      inner = '<div class="htsi-empty">The games for this chapter are not ready yet. Please come back soon.</div>';
    }
    html += section(k, 2, 'games', 'Play Fill', 'Build each phrase before your time runs out', inner);

    // 3. exercises, 4. key
    function exRows(path) {
      return '<div class="htsi-list">' + ch.exercises.map(function (e, i) {
        return '<a class="htsi-row" href="' + base + path + i + '"><span class="htsi-tag">' + esc(e.tag || ('EX ' + (i + 1))) + '</span>' +
          '<span class="htsi-row-main"><span class="htsi-row-title">' + esc(e.title) + '</span></span><span style="color:var(--blue)">' + ICON.right + '</span></a>';
      }).join('') + '</div>';
    }
    html += section(k, 3, 'ex', 'Interactive exercises', 'The same exercises as on the back of your map',
      hasEx ? exRows('/ex/') : '<div class="htsi-empty">The exercises for this chapter are not ready yet. Please come back soon.</div>');
    html += section(k, 4, 'key', 'Key to exercises', 'Answers and notes, for learners and teachers',
      hasEx ? exRows('/key/') : '<div class="htsi-empty">The key for this chapter is not ready yet. Please come back soon.</div>');

    return '<div class="htsi-screen">' + hero(r, ch.title, '#/', 'All chapters') + '<div class="htsi-main">' + html + '</div></div>';
  }

  /* ----- exercises ----- */

  function work(r) {
    var k = r.v + '-' + r.c + '-' + r.i;
    if (!S.work[k]) { S.work[k] = { picks: {}, fills: {}, active: null, checked: false, text: {}, shown: {} }; }
    return S.work[k];
  }

  function gapNumbers(e) {
    var out = [];
    (e.dialogues || []).forEach(function (d) {
      (d.lines || []).forEach(function (l) {
        String(l).replace(/\{(\d+)\}/g, function (m, n) { out.push(n); return m; });
      });
    });
    return out;
  }

  function firstEmpty(e, w) {
    var nums = gapNumbers(e);
    for (var i = 0; i < nums.length; i++) { if (!w.fills[nums[i]]) { return nums[i]; } }
    return null;
  }

  function exChoice(e, w) {
    return (e.items || []).map(function (it, i) {
      var picked = w.picks[i];
      var done = picked !== undefined;
      var opts = (it.options || []).map(function (o, j) {
        var cls = '', note = '';
        if (done && j === it.answer) { cls = ' is-right'; note = ' — correct'; }
        else if (done && j === picked) { cls = ' is-wrong'; note = ' — not this one'; }
        return '<button type="button" class="htsi-opt' + cls + '" data-act="pick" data-i="' + i + '" data-j="' + j + '"><strong>' + 'abcdef'.charAt(j) + ')</strong><span>' + esc(o) + esc(note) + '</span></button>';
      }).join('');
      return '<div class="htsi-card"><div class="htsi-itemnum">' + (i + 1) + ' OF ' + e.items.length + '</div>' +
        '<div class="htsi-lines">' + (it.lines || []).map(function (l) { return '<div>' + rich(l) + '</div>'; }).join('') + '</div>' +
        '<div class="htsi-opts">' + opts + '</div></div>';
    }).join('');
  }

  function exGapfill(e, w) {
    if (!w.checked && (w.active === null || w.active === undefined)) { w.active = firstEmpty(e, w); }
    var answers = e.answers || {};
    var html = (e.dialogues || []).map(function (d) {
      var lines = (d.lines || []).map(function (l) {
        return '<div>' + rich(l).replace(/\{(\d+)\}/g, function (m, n) {
          var fill = w.fills[n];
          var cls = fill ? ' is-filled' : '';
          var label = 'Gap ' + n + (fill ? ': ' + fill : ', empty');
          if (w.checked && fill) {
            var ok = fill === answers[n];
            cls += ok ? ' is-right' : ' is-wrong';
            label += ok ? ', correct' : ', not correct';
          } else if (String(w.active) === String(n)) {
            cls += ' is-active';
          }
          return '<button type="button" class="htsi-gap' + cls + '" data-act="gap" data-n="' + n + '" aria-label="' + esc(label) + '">(' + n + ') ' + (fill ? esc(fill) : '…') + '</button>';
        }) + '</div>';
      }).join('');
      return '<div class="htsi-card"><h2>' + esc(d.title) + '</h2><div class="htsi-lines">' + lines + '</div></div>';
    }).join('');

    var nums = gapNumbers(e);
    var filled = nums.filter(function (n) { return w.fills[n]; }).length;
    if (w.checked) {
      var right = nums.filter(function (n) { return w.fills[n] && w.fills[n] === answers[n]; }).length;
      html += '<p class="htsi-result" role="status">' + right + ' of ' + nums.length + ' correct.' + (right === nums.length ? ' Well done!' : ' Tap a red gap to change it.') + '</p>';
    }
    return { body: html, bank: bank(e, w, filled, nums.length) };
  }

  function bank(e, w, filled, total) {
    var used = {};
    Object.keys(w.fills).forEach(function (n) { if (w.fills[n]) { used[w.fills[n]] = true; } });
    var chips = (e.bank || []).map(function (b, i) {
      return '<button type="button" class="htsi-bankchip' + (used[b] ? ' is-used' : '') + '" data-act="chip" data-b="' + i + '"' + (used[b] ? ' aria-disabled="true"' : '') + '>' + esc(b) + '</button>';
    }).join('');
    var label = w.active ? 'Tap an expression for gap ' + esc(w.active) + '.' : 'Tap a gap to change it.';
    return '<div class="htsi-bank"><div class="htsi-bank-label">' + label + ' ' + filled + ' of ' + total + ' done.</div>' +
      '<div class="htsi-chips">' + chips + '</div>' +
      (filled === total && !w.checked ? '<button type="button" class="htsi-btn" data-act="check">Check my answers</button>' : '') +
    '</div>';
  }

  function exUpgrade(e, w) {
    return (e.items || []).map(function (it, i) {
      var shown = w.shown[i];
      var sug = (it.suggested || []).join(' / ');
      return '<div class="htsi-card"><div class="htsi-itemnum">' + (i + 1) + ' OF ' + e.items.length + '</div>' +
        '<div class="htsi-lines">' + (it.lines || []).map(function (l) { return '<div>' + rich(l) + '</div>'; }).join('') + '</div>' +
        (it.hint ? '<div class="htsi-hint">Hint: ' + esc(it.hint) + '</div>' : '') +
        '<div class="htsi-field"><label for="htsi-up-' + i + '">Your new opener</label>' +
          '<textarea class="htsi-textarea" id="htsi-up-' + i + '" data-input="up" data-i="' + i + '" rows="2">' + esc(w.text[i] || '') + '</textarea></div>' +
        (shown
          ? '<div class="htsi-suggest" role="status">Suggested answer<strong>' + esc(sug) + '</strong>Other answers can be correct too.</div>'
          : '<button type="button" class="htsi-btn-ghost" data-act="suggest" data-i="' + i + '">Show a suggested answer</button>') +
      '</div>';
    }).join('');
  }

  function exSpeaking(e) {
    return '<div class="htsi-card">' + (e.lead ? '<h2>' + esc(e.lead) + '</h2>' : '') +
      '<ul class="htsi-prompts">' + (e.prompts || []).map(function (p) { return '<li>' + esc(p) + '</li>'; }).join('') + '</ul></div>';
  }

  function viewExercise(r, ch) {
    var e = ch.exercises && ch.exercises[r.i];
    var base = '#/' + r.v + '/' + r.c;
    if (!e) { return viewChapter(r, ch); }
    var w = work(r);
    var body = '', bankHtml = '';
    if (e.type === 'choice') { body = exChoice(e, w); }
    else if (e.type === 'gapfill') { var g = exGapfill(e, w); body = g.body; bankHtml = g.bank; }
    else if (e.type === 'upgrade') { body = exUpgrade(e, w); }
    else { body = exSpeaking(e); }

    var nav = '<div class="htsi-exnav">' +
      (r.i > 0 ? '<a class="htsi-btn-ghost" href="' + base + '/ex/' + (r.i - 1) + '">' + ICON.left + ' Back</a>' : '') +
      (r.i < ch.exercises.length - 1
        ? '<a class="htsi-btn-ghost" href="' + base + '/ex/' + (r.i + 1) + '">Next ' + ICON.right + '</a>'
        : '<a class="htsi-btn-ghost" href="' + base + '/key/0">Key</a>') +
    '</div>';

    return '<div class="htsi-screen">' +
      '<div class="htsi-exhead"><a class="htsi-back" href="' + base + '">' + ICON.left + esc(ch.title) + '</a>' +
        '<div class="htsi-eyebrow">' + esc(e.tag || ('EX ' + (r.i + 1))) + '</div><h1>' + esc(e.title) + '</h1><p>' + esc(e.instruction || '') + '</p></div>' +
      '<div class="htsi-exbody">' + body + nav + '</div>' + bankHtml +
    '</div>';
  }

  function noteBox(label, text) {
    return text ? '<div class="htsi-note"><span class="htsi-note-label">' + esc(label) + '</span><p>' + note(text) + '</p></div>' : '';
  }

  function keyChoice(e) {
    var label = e.note_label || 'Note';
    return (e.items || []).map(function (it, n) {
      var exp = bold(it.lines);
      return '<div class="htsi-card htsi-kcard"><div class="htsi-itemnum">' + (n + 1) + ' OF ' + e.items.length + '</div>' +
        (exp ? '<div class="htsi-kexp">' + esc(exp) + '</div>' : '') +
        '<div class="htsi-kans"><strong>' + 'abcdef'.charAt(it.answer) + ')</strong><span>' + esc((it.options || [])[it.answer]) + '</span></div>' +
        noteBox(label, it.note) + '</div>';
    }).join('');
  }

  function keyGapfill(e) {
    var ans = e.answers || {}, used = {};
    var html = (e.dialogues || []).map(function (d) {
      var rows = [];
      (d.lines || []).forEach(function (l) {
        String(l).replace(/\{(\d+)\}/g, function (m, n) {
          used[ans[n]] = true;
          rows.push('<li><span class="htsi-kn">' + esc(n) + '</span><span>' + esc(ans[n]) + '</span></li>');
          return m;
        });
      });
      return '<div class="htsi-card htsi-kcard"><h2>' + esc(d.title) + '</h2><ol class="htsi-keylist">' + rows.join('') + '</ol></div>';
    }).join('');
    var extra = (e.bank || []).filter(function (b) { return !used[b]; });
    if (extra.length) {
      html += '<div class="htsi-card htsi-kcard"><h2>Not needed</h2><p class="htsi-ktext">' + esc(extra.join(' · ')) + '</p></div>';
    }
    if (e.near_misses && e.near_misses.length) {
      html += '<h2 class="htsi-khead">Near-misses</h2>' + e.near_misses.map(function (m) {
        return '<div class="htsi-card htsi-kcard"><div class="htsi-kexp">' + esc(m.expression) + '</div>' +
          (m.tempting_for ? '<div class="htsi-kmeta">Tempting for: <strong>' + esc(m.tempting_for) + '</strong></div>' : '') +
          noteBox('What rules it out', m.why) + '</div>';
      }).join('');
    }
    return html;
  }

  function keyUpgrade(e) {
    return (e.items || []).map(function (it, n) {
      var exp = bold(it.lines);
      return '<div class="htsi-card htsi-kcard"><div class="htsi-itemnum">' + (n + 1) + ' OF ' + e.items.length + '</div>' +
        (exp ? '<div class="htsi-kold">' + esc(exp) + '</div>' : '') +
        '<div class="htsi-kexp">' + esc((it.suggested || []).join(' / ')) + '</div>' +
        noteBox(e.note_label || 'Note', it.note) + '</div>';
    }).join('');
  }

  function keySpeaking(e) {
    if (!e.checklist || !e.checklist.length) { return ''; }
    return '<div class="htsi-card htsi-kcard"><ul class="htsi-checklist">' + e.checklist.map(function (c) {
      return '<li><span class="htsi-box" aria-hidden="true"></span><span>' + note(c) + '</span></li>';
    }).join('') + '</ul></div>';
  }

  function viewKey(r, ch) {
    var list = ch.exercises || [];
    var e = list[r.i];
    var base = '#/' + r.v + '/' + r.c;
    if (!e) { return viewChapter(r, ch); }
    var body, intro = e.key_intro || '';
    if (e.type === 'choice') { body = keyChoice(e); }
    else if (e.type === 'gapfill') { body = keyGapfill(e); }
    else if (e.type === 'upgrade') { body = keyUpgrade(e); intro = intro || 'Suggested answers. Other answers can be correct too.'; }
    else { body = keySpeaking(e); intro = intro || 'Speaking task. There is no key.'; }

    var nav = '<div class="htsi-exnav">' +
      (r.i > 0 ? '<a class="htsi-btn-ghost" href="' + base + '/key/' + (r.i - 1) + '">' + ICON.left + ' Back</a>' : '') +
      (r.i < list.length - 1
        ? '<a class="htsi-btn-ghost" href="' + base + '/key/' + (r.i + 1) + '">Next ' + ICON.right + '</a>'
        : '<a class="htsi-btn-ghost" href="' + base + '">Chapter</a>') +
    '</div>';

    return '<div class="htsi-screen">' +
      '<div class="htsi-exhead"><a class="htsi-back" href="' + base + '">' + ICON.left + esc(ch.title) + '</a>' +
        '<div class="htsi-eyebrow">Key · ' + esc(e.tag || ('EX ' + (r.i + 1))) + '</div><h1>' + esc(e.title) + '</h1>' +
        (intro ? '<p class="htsi-kintro">' + note(intro) + '</p>' : '') + '</div>' +
      '<div class="htsi-exbody">' + body + nav + '</div></div>';
  }

  /* ---------- render ---------- */

  var lastScreen = '';

  function render() {
    var r = route();
    var html, screen = location.hash;

    if (!S.loaded) {
      html = S.loadError
        ? '<div class="htsi-loading"><div><p role="alert">' + esc(S.loadError) + '</p><p style="margin-top:16px"><button type="button" class="htsi-btn-ghost" data-act="reload">Try again</button></p></div></div>'
        : viewLoading();
    } else if (!S.reader) {
      html = viewLogin(r);
      screen = 'login';
    } else if (r.name === 'home') {
      html = viewHome();
    } else if (!unlocked(r.v)) {
      S.vol = r.v;
      html = viewHome();
      screen = 'home-locked';
    } else {
      var ch = S.chapters[r.v + '-' + r.c];
      if (!ch) { loadChapter(r.v, r.c); ch = S.chapters[r.v + '-' + r.c]; }
      if (ch.loading) {
        html = viewLoading();
      } else if (ch.error) {
        html = '<div class="htsi-loading"><div><p role="alert">' + esc(ch.error) + '</p><p style="margin-top:16px"><a class="htsi-btn-ghost" style="display:inline-block;text-decoration:none" href="#/">All chapters</a></p></div></div>';
      } else if (r.name === 'ex') {
        html = viewExercise(r, ch);
      } else if (r.name === 'key') {
        html = viewKey(r, ch);
      } else {
        html = viewChapter(r, ch);
      }
    }

    app.innerHTML = html;
    if (screen !== lastScreen) {
      lastScreen = screen;
      window.scrollTo(0, 0);
    }
  }

  /* ---------- actions ---------- */

  function formError(form, msg) {
    var box = form.querySelector('.htsi-error');
    if (!box) { return; }
    box.textContent = msg || '';
    box.hidden = !msg;
  }

  app.addEventListener('submit', function (ev) {
    var form = ev.target;
    var kind = form.getAttribute('data-form');
    if (!kind) { return; }
    ev.preventDefault();
    if (S.busy) { return; }
    var btn = form.querySelector('button[type="submit"]');
    var body;
    if (kind === 'login') {
      body = { code: form.code.value, email: form.email.value.trim(), name: form.name.value.trim(), news: form.news.checked };
      if (!body.code.trim()) { formError(form, 'Please type the code from your book.'); form.code.focus(); return; }
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(body.email)) { formError(form, 'Please check your email address.'); form.email.focus(); return; }
    } else {
      body = { code: form.code.value };
      if (!body.code.trim()) { formError(form, 'Please type the code from your book.'); form.code.focus(); return; }
    }
    S.busy = true;
    formError(form, '');
    if (btn) { btn.disabled = true; }
    api('POST', kind === 'login' ? '/login' : '/unlock', body).then(function (data) {
      S.busy = false;
      applyState(data);
      var r = route();
      if (r.name === 'home' && S.reader && S.reader.volumes.length) {
        S.vol = kind === 'unlock' ? S.vol : S.reader.volumes[0];
      }
      render();
    }, function (err) {
      S.busy = false;
      if (btn) { btn.disabled = false; }
      formError(form, err.message);
    });
  });

  app.addEventListener('input', function (ev) {
    var t = ev.target;
    if (t.getAttribute('data-input') === 'up') {
      work(route()).text[t.getAttribute('data-i')] = t.value;
    }
  });

  app.addEventListener('change', function (ev) {
    var t = ev.target;
    if (t.getAttribute('data-act') === 'news') {
      var on = t.checked;
      api('POST', '/news', { on: on }).then(function (data) {
        if (data.reader) { S.reader = data.reader; }
      }, function () {
        t.checked = !on;
      });
    }
  });

  app.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-act]') : null;
    if (!t || !app.contains(t)) { return; }
    var act = t.getAttribute('data-act');
    var r = route();
    var ch, e, w;

    if (act === 'news') { return; }
    if (act === 'reload') { boot(); return; }
    if (act === 'vol') { S.vol = parseInt(t.getAttribute('data-n'), 10); if (r.name !== 'home') { lastScreen = ''; location.hash = '#/'; } else { render(); } return; }
    if (act === 'logout') {
      api('POST', '/logout').then(function (data) { applyState(data); location.hash = '#/'; render(); }, function () { render(); });
      return;
    }
    if (act === 'toggle') {
      var kk = r.v + '-' + r.c, id = t.getAttribute('data-s');
      if (!S.open[kk]) { S.open[kk] = {}; }
      S.open[kk][id] = !S.open[kk][id];
      render();
      var head = app.querySelector('[data-act="toggle"][data-s="' + id + '"]');
      if (head) { head.focus({ preventScroll: true }); }
      return;
    }
    if (act === 'sendmap') {
      var k = r.v + '-' + r.c;
      S.busy = true; render();
      api('POST', '/send-map', { v: r.v, c: r.c }).then(function (data) {
        S.busy = false; S.mapSent[k] = { to: data.sent_to }; render();
      }, function (err) {
        S.busy = false; S.mapSent[k] = { error: err.message }; render();
      });
      return;
    }

    if (r.name !== 'ex') { return; }
    ch = S.chapters[r.v + '-' + r.c];
    e = ch && ch.exercises && ch.exercises[r.i];
    if (!e) { return; }
    w = work(r);

    if (act === 'pick') {
      w.picks[t.getAttribute('data-i')] = parseInt(t.getAttribute('data-j'), 10);
    } else if (act === 'suggest') {
      w.shown[t.getAttribute('data-i')] = true;
    } else if (act === 'gap') {
      var n = t.getAttribute('data-n');
      if (w.fills[n]) { delete w.fills[n]; }
      w.active = n;
      w.checked = false;
    } else if (act === 'chip') {
      var text = (e.bank || [])[parseInt(t.getAttribute('data-b'), 10)];
      var inUse = Object.keys(w.fills).some(function (g) { return w.fills[g] === text; });
      if (inUse || !w.active) { return; }
      w.fills[w.active] = text;
      w.active = firstEmpty(e, w);
      w.checked = false;
    } else if (act === 'check') {
      w.checked = true;
      w.active = null;
    }
    render();
  });

  window.addEventListener('hashchange', render);

  function boot() {
    S.loaded = false; S.loadError = '';
    render();
    api('GET', '/state').then(function (data) {
      applyState(data);
      S.loaded = true;
      var r = route();
      if (r.v) { S.vol = r.v; } else if (S.reader && S.reader.volumes.length) { S.vol = S.reader.volumes[0]; }
      render();
    }, function (err) {
      S.loadError = err.message;
      render();
    });
  }

  boot();
})();
