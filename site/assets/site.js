// Dromos site: benchmark race switcher, code tabs and copy buttons. No dependencies.
(function () {
  'use strict';

  // µs per request to a route with a parameter, from the 25 Oct 2026 dataset
  var RACES = {
    w2000: {
      note: 'At 2,000 routes Dromos lean is the fastest of all eight. FastRoute is 27 times slower here than with 20 routes.',
      times: { 'Dromos lean': 0.83, 'Symfony Routing': 1.36, 'Dromos full': 2.43, 'FastRoute': 16.7, 'Slim': 29.1, 'Laravel': 41.1, 'Mezzio': 141, 'Flight': 1373 }
    },
    w20: {
      note: 'With only 20 routes FastRoute\'s matcher wins by 0.2 µs. Dromos lean is second, ahead of Symfony Routing.',
      times: { 'FastRoute': 0.61, 'Dromos lean': 0.81, 'Symfony Routing': 0.87, 'Dromos full': 2.40, 'Slim': 11.1, 'Mezzio': 11.5, 'Flight': 25.8, 'Laravel': 39.9 }
    },
    f2000: {
      note: 'A fresh request that loads its routes from each router\'s cache. Slim and Mezzio still register every route; Flight has no cache.',
      times: { 'Dromos lean': 1.74, 'Symfony Routing': 2.16, 'Dromos full': 4.45, 'FastRoute': 20.5, 'Laravel': 66.3, 'Slim': 1653, 'Mezzio': 2925, 'Flight': 4889 }
    },
    f20: {
      note: 'With 20 routes Symfony Routing is 0.07 µs ahead of Dromos lean, inside the run-to-run spread.',
      times: { 'Symfony Routing': 1.66, 'Dromos lean': 1.73, 'FastRoute': 2.21, 'Dromos full': 4.46, 'Slim': 47.1, 'Laravel': 63.8, 'Mezzio': 72.6, 'Flight': 74.4 }
    }
  };

  // Log scale from 0.1 µs to 10,000 µs
  function width(us) { return ((Math.log10(us) + 1) / 5 * 100).toFixed(1) + '%'; }
  function fmt(us) { return us >= 1000 ? us.toLocaleString('en-GB') : String(us); }
  function rate(us) {
    var n = 1e6 / us;
    if (n >= 1e6) return (n / 1e6).toFixed(1) + 'M/s';
    if (n >= 1e4) return Math.round(n / 1e3) + 'k/s';
    if (n >= 1e3) return (n / 1e3).toFixed(1) + 'k/s';
    return Math.round(n) + '/s';
  }

  var lanes = document.getElementById('lanes');
  var note = document.getElementById('track-note');
  var raceTabs = document.querySelectorAll('[data-race]');

  function showRace(key) {
    var race = RACES[key];
    if (!race || !lanes) return;
    var names = Object.keys(race.times).sort(function (a, b) { return race.times[a] - race.times[b]; });
    var existing = {};
    Array.prototype.forEach.call(lanes.children, function (li) {
      existing[li.querySelector('.name').textContent] = li;
    });
    names.forEach(function (name, i) {
      var li = existing[name];
      var us = race.times[name];
      li.querySelector('.pos').textContent = i + 1;
      li.querySelector('.t').textContent = fmt(us) + ' µs';
      li.querySelector('.rps').textContent = rate(us);
      li.querySelector('.run i').style.setProperty('--w', width(us));
      lanes.appendChild(li);
    });
    note.textContent = race.note;
    raceTabs.forEach(function (tab) { tab.setAttribute('aria-selected', String(tab.dataset.race === key)); });
  }

  raceTabs.forEach(function (tab) {
    tab.addEventListener('click', function () { showRace(tab.dataset.race); });
  });

  // Code tabs
  var codeTabs = document.querySelectorAll('.tabs [role="tab"]');
  codeTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      codeTabs.forEach(function (t) {
        var selected = t === tab;
        t.setAttribute('aria-selected', String(selected));
        document.getElementById(t.getAttribute('aria-controls')).hidden = !selected;
      });
    });
  });

  // Arrow keys move between tabs in either tab list
  document.querySelectorAll('[role="tablist"]').forEach(function (list) {
    list.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
      var i = tabs.indexOf(document.activeElement);
      if (i < 0) return;
      var next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
      next.focus();
      next.click();
    });
  });

  // Copy buttons
  function copy(button, text) {
    function done() {
      button.classList.add('done');
      button.textContent = 'Copied';
      setTimeout(function () { button.classList.remove('done'); button.textContent = 'Copy'; }, 1600);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, function () { select(button); });
    } else {
      select(button);
    }
  }
  function select(button) {
    var target = button.previousElementSibling;
    if (!target) return;
    var range = document.createRange();
    range.selectNodeContents(target);
    var sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  }
  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      copy(button, document.querySelector(button.dataset.copy).textContent.trim());
    });
  });
  var activeCopy = document.querySelector('[data-copy-active]');
  if (activeCopy) {
    activeCopy.addEventListener('click', function () {
      var pre = document.querySelector('.code-card pre:not([hidden])');
      if (pre) copy(activeCopy, pre.textContent);
    });
  }
})();
