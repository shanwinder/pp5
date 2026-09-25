(async () => {
  'use strict';
  if (window === window.top) {
    const results = new Map();
    window.addEventListener('message', event => {
      if (event.origin !== location.origin || event.data?.type !== 'pp5-confirmation-check') return;
      const frame = Array.from(document.querySelectorAll('iframe')).find(item => item.contentWindow === event.source);
      if (!frame) return;
      results.set(frame.title, event.data.result);
      document.getElementById('browser-results').textContent = Array.from(results, ([key, value]) => `${key}: ${value}`).join('\n');
      if (results.size === 2) document.title = Array.from(results.values()).every(value => value.startsWith('PASS')) ? 'PASS — PP5 confirmation' : 'FAIL — PP5 confirmation';
    });
    return;
  }
  const checks = [];
  const assert = (ok, label) => { if (!ok) throw new Error(label); checks.push(label); };
  const originalConfirm = window.confirm;
  try {
    const form = document.querySelector('form[action="/system/schools/1/status"]');
    const ordinary = document.getElementById('ordinary');
    const receipt = document.querySelector('iframe[name="receipt"]');
    let calls = 0;
    let accepted = false;
    let prevented;
    window.confirm = message => {
      calls++;
      assert(message === 'ยืนยันการเปลี่ยนสถานะโรงเรียนหรือไม่? การระงับหรือปิดใช้งานโรงเรียนจะหยุดการเข้าถึงข้อมูลในบริบทโรงเรียนนั้น', 'static Thai warning');
      return accepted;
    };
    // Observe after app.js; do not prevent submission in the test harness.
    form.addEventListener('submit', event => { prevented = event.defaultPrevented; });
    for (const item of [form, ordinary]) item.target = 'receipt';
    form.elements.status.value = 'SUSPENDED';
    const post = item => new Promise((resolve, reject) => {
      const timeout = setTimeout(() => reject(new Error('Native POST did not arrive')), 5000);
      receipt.onload = () => {
        const result = receipt.contentDocument.getElementById('receipt');
        if (!result) return;
        clearTimeout(timeout);
        resolve(JSON.parse(result.textContent));
      };
      item.requestSubmit(item.querySelector('button[type="submit"]'));
    });
    assert(form.hasAttribute('data-confirm'), 'status form explicitly opts in');
    assert(!ordinary.hasAttribute('data-confirm'), 'ordinary danger button has no opt-in');
    if (location.pathname !== '/no-js') {
      form.requestSubmit(form.querySelector('button[type="submit"]'));
      assert(calls === 1 && prevented === true, 'rejection prevents native submission');
      await new Promise(resolve => setTimeout(resolve, 150));
      assert(!receipt.contentDocument.getElementById('receipt'), 'rejection produces no POST receipt');
      accepted = true;
    }
    const result = await post(form);
    assert(prevented === false, 'acceptance or no JS leaves submission uncanceled');
    assert(result.method === 'POST' && result.path === '/system/schools/1/status', 'native method and action unchanged');
    assert(JSON.stringify(result.fields) === JSON.stringify({_token:'fixture-only',status:'SUSPENDED'}), 'POST fields unchanged; no confirmation authority field');
    assert(calls === (location.pathname === '/no-js' ? 0 : 2), 'confirmation only when enhanced');
    const before = calls;
    const normal = await post(ordinary);
    assert(calls === before && normal.path === '/ordinary' && normal.fields.value === 'unchanged', 'unmarked danger button submits without confirmation');
    window.parent.postMessage({type:'pp5-confirmation-check',result:`PASS ${checks.length} checks`}, location.origin);
  } catch (error) {
    window.parent.postMessage({type:'pp5-confirmation-check',result:`FAIL after ${checks.length}: ${error.message}`}, location.origin);
  } finally { window.confirm = originalConfirm; }
})();
