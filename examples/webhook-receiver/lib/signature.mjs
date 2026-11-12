import { createHmac, timingSafeEqual } from 'node:crypto';

const SIGNATURE_VERSION = 'v1';

/**
 * Produces the exact header understood by the Laravel sender. Keeping this
 * implementation dependency-free makes it useful as an integration fixture.
 */
export function createSignatureHeader({ secret, timestamp, rawBody }) {
  const signature = createHmac('sha256', secret)
    .update(`${timestamp}.`)
    .update(rawBody)
    .digest('hex');

  return `t=${timestamp},${SIGNATURE_VERSION}=${signature}`;
}

export function verifySignature({
  header,
  secret,
  rawBody,
  now = Math.floor(Date.now() / 1000),
  toleranceSeconds = 300,
}) {
  const parsed = parseSignatureHeader(header);

  if (parsed === null) {
    return { valid: false, reason: 'malformed_signature' };
  }

  if (Math.abs(now - parsed.timestamp) > toleranceSeconds) {
    return { valid: false, reason: 'expired_signature' };
  }

  const expected = createHmac('sha256', secret)
    .update(`${parsed.timestamp}.`)
    .update(rawBody)
    .digest();

  const received = Buffer.from(parsed.signature, 'hex');

  // timingSafeEqual throws on unequal lengths, which must remain a normal
  // authentication failure rather than an avoidable 500 response.
  if (received.length !== expected.length) {
    return { valid: false, reason: 'invalid_signature' };
  }

  return timingSafeEqual(received, expected)
    ? { valid: true, timestamp: parsed.timestamp }
    : { valid: false, reason: 'invalid_signature' };
}

function parseSignatureHeader(header) {
  if (typeof header !== 'string' || header.length > 512) {
    return null;
  }

  const parts = Object.fromEntries(
    header.split(',').map((segment) => {
      const [key, value] = segment.trim().split('=', 2);

      return [key, value];
    }),
  );

  if (!/^\d+$/.test(parts.t ?? '') || !/^[a-f\d]{64}$/i.test(parts[SIGNATURE_VERSION] ?? '')) {
    return null;
  }

  const timestamp = Number(parts.t);

  if (!Number.isSafeInteger(timestamp)) {
    return null;
  }

  return {
    timestamp,
    signature: parts[SIGNATURE_VERSION],
  };
}
