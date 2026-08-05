/**
 * Rotator state-machine tests.
 *
 * Runs the real assets/js/rotator.js in jsdom with a controllable clock and a
 * stub Swiper, so the advance timer, pause/resume and tab clicks can be checked
 * without a browser.
 *
 *   npm install && npm test
 */
const { JSDOM } = require('jsdom');
const fs = require('fs');

function build({ slides = 3, pauseOnHover = true, delay = 6000, reduce = false } = {}) {
  const tabs = Array.from({length: slides}, (_, i) =>
    `<button class="refp-rotator__tab${i===0?' is-active':''}" data-index="${i}">
       <span class="refp-rotator__tab-track"><span class="refp-rotator__tab-fill"></span></span>
       <span class="refp-rotator__tab-label">T${i}</span></button>`).join('');
  const panes = Array.from({length: slides}, (_, i) =>
    `<div class="swiper-slide refp-rotator__slide">S${i}</div>`).join('');

  const dom = new JSDOM(`<!DOCTYPE html><body>
    <div class="refp-rotator" data-refp-config='${JSON.stringify({delay, speed:500, pauseOnHover})}'>
      <div class="swiper refp-rotator__swiper"><div class="swiper-wrapper">${panes}</div></div>
      <div class="refp-rotator__tabs">${tabs}</div>
    </div></body>`, { runScripts: 'outside-only', pretendToBeVisual: false });

  const w = dom.window;

  // Controllable clock.
  const clock = { now: 0, queue: [], nextId: 1 };
  w.setTimeout = (fn, ms) => { const id = clock.nextId++; clock.queue.push({id, at: clock.now + ms, fn}); return id; };
  w.clearTimeout = (id) => { clock.queue = clock.queue.filter(t => t.id !== id); };
  clock.tick = (ms) => {
    const target = clock.now + ms;
    for (;;) {
      const due = clock.queue.filter(t => t.at <= target).sort((a,b)=>a.at-b.at)[0];
      if (!due) break;
      clock.queue = clock.queue.filter(t => t !== due);
      clock.now = due.at;
      due.fn();
    }
    clock.now = target;
  };

  w.matchMedia = () => ({ matches: reduce });

  // Track bar widths so getComputedStyle can report a partial fill.
  const barState = new Map();
  const realGCS = w.getComputedStyle.bind(w);
  w.getComputedStyle = (el) => {
    if (el && el.classList && el.classList.contains('refp-rotator__tab-fill')) {
      return { width: (barState.get(el) ?? 0) + 'px' };
    }
    return realGCS(el);
  };
  Object.defineProperty(w.HTMLElement.prototype, 'offsetWidth', {
    get() { return this.classList.contains('refp-rotator__tab-track') ? 100 : 50; },
    configurable: true
  });

  // Minimal Swiper: fade + loop + event emitter.
  const events = {};
  const swiper = {
    realIndex: 0,
    slideNext() { this.realIndex = (this.realIndex + 1) % slides; emit('slideChangeTransitionStart'); },
    slideToLoop(i) { if (i === this.realIndex) return; this.realIndex = i; emit('slideChangeTransitionStart'); },
    slideTo(i) { this.slideToLoop(i); },
    on(name, fn) { (events[name] = events[name] || []).push(fn); }
  };
  function emit(name) { (events[name] || []).forEach(fn => fn()); }
  w.Swiper = function () { return swiper; };

  // Advance the fake bar to match whatever transition was last set.
  const setBar = (el, frac) => barState.set(el, Math.round(frac * 100));

  const script = fs.readFileSync(__dirname + '/../assets/js/rotator.js', 'utf8');
  w.eval(script);
  // jsdom reports readyState 'loading' at this point, so the script is waiting
  // on DOMContentLoaded. Fire it to trigger init.
  w.document.dispatchEvent(new w.Event('DOMContentLoaded'));

  const root = w.document.querySelector('.refp-rotator');
  return {
    w, clock, swiper, root, barState, setBar, slides,
    activeTab: () => Array.from(root.querySelectorAll('.refp-rotator__tab')).findIndex(t => t.classList.contains('is-active')),
    pending: () => clock.queue.length,
    fill: (i) => root.querySelectorAll('.refp-rotator__tab-fill')[i],
    hover: () => root.dispatchEvent(new w.MouseEvent('mouseenter')),
    unhover: () => root.dispatchEvent(new w.MouseEvent('mouseleave')),
    clickTab: (i) => root.querySelectorAll('.refp-rotator__tab')[i].dispatchEvent(new w.MouseEvent('click', {bubbles:true})),
  };
}

let pass = 0, fail = 0;
function check(label, got, want) {
  const ok = JSON.stringify(got) === JSON.stringify(want);
  console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${label}${ok ? '' : `  got ${JSON.stringify(got)} want ${JSON.stringify(want)}`}`);
  ok ? pass++ : fail++;
}

console.log('\n[1] advances on its own');
{
  const t = build();
  check('starts on tab 0', t.activeTab(), 0);
  check('one timer queued', t.pending(), 1);
  t.clock.tick(6000);
  check('advanced to tab 1', t.activeTab(), 1);
  t.clock.tick(6000);
  check('advanced to tab 2', t.activeTab(), 2);
  t.clock.tick(6000);
  check('looped back to tab 0', t.activeTab(), 0);
  check('still one timer queued', t.pending(), 1);
}

console.log('\n[2] hover pauses, unhover RESUMES (the reported bug)');
{
  const t = build();
  t.clock.tick(2000);
  t.setBar(t.fill(0), 0.25);           // bar is exactly a quarter full
  t.hover();
  check('timer cleared on hover', t.pending(), 0);
  t.clock.tick(20000);
  check('does not advance while hovered', t.activeTab(), 0);
  t.unhover();
  check('timer requeued on unhover', t.pending(), 1);
  t.clock.tick(4499);
  check('not advanced at 4499ms of the 4500ms remaining', t.activeTab(), 0);
  t.clock.tick(2);
  check('advances once the 4500ms remaining elapses', t.activeTab(), 1);
  check('and keeps going', (t.clock.tick(6000), t.activeTab()), 2);
}

console.log('\n[3] clicking a tab keeps the rotation alive (the reported bug)');
{
  const t = build();
  t.hover();                            // you must hover to click
  t.clickTab(2);
  check('jumped to tab 2', t.activeTab(), 2);
  t.unhover();
  check('timer running after click+unhover', t.pending(), 1);
  t.clock.tick(6000);
  check('advanced past the clicked slide', t.activeTab(), 0);
}

console.log('\n[4] clicking the ALREADY-active tab restarts its cycle');
{
  const t = build();
  t.clock.tick(3000);
  t.clickTab(0);
  check('still on tab 0', t.activeTab(), 0);
  check('exactly one timer (not two)', t.pending(), 1);
  t.clock.tick(5999);
  check('full delay restarted, not 3000 left', t.activeTab(), 0);
  t.clock.tick(2);
  check('then advances', t.activeTab(), 1);
}

console.log('\n[5] no double-scheduling under repeated events');
{
  const t = build();
  t.hover(); t.hover(); t.hover();
  check('repeated hover leaves no timer', t.pending(), 0);
  t.unhover(); t.unhover(); t.unhover();
  check('repeated unhover leaves exactly one', t.pending(), 1);
  t.clickTab(1); t.clickTab(2); t.clickTab(0);
  check('rapid clicks leave exactly one', t.pending(), 1);
}

console.log('\n[6] single slide does not rotate');
{
  const t = build({ slides: 1 });
  check('no timer queued', t.pending(), 0);
  t.clock.tick(60000);
  check('stays put', t.activeTab(), 0);
}

console.log('\n[7] prefers-reduced-motion stops rotation');
{
  const t = build({ reduce: true });
  check('static class applied', t.root.classList.contains('refp-rotator--static'), true);
  check('no timer queued', t.pending(), 0);
  t.clock.tick(60000);
  check('never advances', t.activeTab(), 0);
}

console.log('\n[8] pauseOnHover off means hover is ignored');
{
  const t = build({ pauseOnHover: false });
  t.hover();
  check('timer still queued', t.pending(), 1);
  t.clock.tick(6000);
  check('advances despite hover', t.activeTab(), 1);
}

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
