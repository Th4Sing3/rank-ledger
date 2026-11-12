import assert from 'node:assert/strict';
import test from 'node:test';
import { createSignatureHeader, verifySignature } from '../lib/signature.mjs';

const secret = 'test-secret';
const timestamp = 1_700_000_000;
const body = Buffer.from('{"id":"evt_123","type":"leaderboard.event.recorded"}');

test('accepts a current, correctly signed raw payload', () => {
  const header = createSignatureHeader({ secret, timestamp, rawBody: body });

  assert.deepEqual(
    verifySignature({ header, secret, rawBody: body, now: timestamp }),
    { valid: true, timestamp },
  );
});

test('rejects a modified payload', () => {
  const header = createSignatureHeader({ secret, timestamp, rawBody: body });

  assert.deepEqual(
    verifySignature({ header, secret, rawBody: Buffer.from('{"id":"evt_other"}'), now: timestamp }),
    { valid: false, reason: 'invalid_signature' },
  );
});

test('rejects a stale signature before comparing it', () => {
  const header = createSignatureHeader({ secret, timestamp, rawBody: body });

  assert.deepEqual(
    verifySignature({ header, secret, rawBody: body, now: timestamp + 301 }),
    { valid: false, reason: 'expired_signature' },
  );
});

test('rejects malformed headers without throwing', () => {
  assert.deepEqual(
    verifySignature({ header: 't=now,v1=not-a-signature', secret, rawBody: body, now: timestamp }),
    { valid: false, reason: 'malformed_signature' },
  );
});
