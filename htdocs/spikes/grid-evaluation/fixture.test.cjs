const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const sandbox = { window: {} };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, 'fixture.js'), 'utf8'), sandbox);
const spike = sandbox.window.GridSpike;

function clearPlanned(fixture, rectangle) {
  const plan = spike.planClear(fixture, rectangle);
  if (plan.ok) {
    for (const cell of plan.cells) cell.row[cell.field] = '';
    for (const y of new Set(plan.cells.map(cell => cell.y))) {
      Object.assign(fixture.rows[y], spike.mockSummary(fixture.rows[y], fixture.components));
    }
  }
  return plan;
}

test('single clear: blank differs from zero; summary total/count change, max stays', () => {
  const fixture = spike.make();
  const row = fixture.rows[2];
  row.s0 = '5'; row.s1 = '10'; row.s2 = '8';
  const before = spike.mockSummary(row, fixture.components);
  const plan = clearPlanned(fixture, { x1:2, y1:2, x2:2, y2:2 });
  assert.equal(plan.ok, true);
  assert.equal(row.s1, '');
  assert.equal(Number(before.sum) - Number(row.sum), 10);
  assert.equal(Number(before.count.split(' / ')[0]) - Number(row.count.split(' / ')[0]), 1);
  assert.equal(row.max, before.max);
  row.s1 = '0';
  const withZero = spike.mockSummary(row, fixture.components);
  assert.equal(withZero.sum, row.sum);
  assert.equal(Number(withZero.count.split(' / ')[0]), Number(row.count.split(' / ')[0]) + 1);
  assert.equal(JSON.stringify(plan.cells.map(cell => [cell.enrollmentId,cell.componentId])), '[[9003,8002]]');
  clearPlanned(fixture, { x1:2, y1:2, x2:2, y2:2 });
  assert.equal(row.s1, '');
});

test('3 by 3 clear, no-op repeat, and exact affected rows', () => {
  const fixture = spike.make();
  const outside = JSON.stringify(fixture.rows[6]);
  const plan = clearPlanned(fixture, { x1:1, y1:2, x2:3, y2:4 });
  assert.equal(plan.ok, true); assert.equal(plan.cells.length, 9);
  assert.deepEqual([plan.width, plan.height], [3,3]);
  assert.ok(plan.cells.every(cell => cell.row[cell.field] === ''));
  assert.equal(JSON.stringify(fixture.rows[6]), outside);
  const before = JSON.stringify(fixture.rows);
  clearPlanned(fixture, { x1:1, y1:2, x2:3, y2:4 });
  assert.equal(JSON.stringify(fixture.rows), before);
});

test('historical, summary, identity, and invalid rectangles reject before any mutation', () => {
  for (const [rectangle, reason] of [
    [{ x1:1,y1:29,x2:3,y2:30 }, 'historical'],
    [{ x1:20,y1:2,x2:21,y2:2 }, 'summary'],
    [{ x1:0,y1:2,x2:1,y2:2 }, 'identity'],
    [{ x1:1,y1:2,x2:1,y2:35 }, 'invalid rectangle']
  ]) {
    const fixture = spike.make(), before = JSON.stringify(fixture.rows);
    const plan = clearPlanned(fixture, rectangle);
    assert.equal(plan.ok, false); assert.equal(plan.reason, reason);
    assert.equal(JSON.stringify(fixture.rows), before);
  }
});

test('completeness requires all nonblank components; zero is entered', () => {
  const fixture = spike.make();
  const row = fixture.rows[0];
  fixture.components.forEach(c => { row[c.field] = '0'; });
  const complete = spike.mockSummary(row, fixture.components);
  assert.equal(complete.sum, '0.00');
  assert.equal(complete.count, '20 / 20');
  assert.equal(complete.complete, 'ครบ');
  row.s0 = '';
  const incomplete = spike.mockSummary(row, fixture.components);
  assert.equal(incomplete.count, '19 / 20');
  assert.equal(incomplete.complete, 'ยังไม่ครบ');
  assert.equal(incomplete.max, complete.max);
});
