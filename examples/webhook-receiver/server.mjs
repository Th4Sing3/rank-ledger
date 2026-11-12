import { createServer } from 'node:http';
import { verifySignature } from './lib/signature.mjs';

const port = parsePort(process.env.WEBHOOK_PORT ?? '8081');
const secret = process.env.WEBHOOK_SECRET ?? 'local-development-webhook-secret';
const responseStatus = parseStatus(process.env.WEBHOOK_RESPONSE_STATUS ?? '200');
const toleranceSeconds = parsePositiveInteger(process.env.WEBHOOK_TOLERANCE_SECONDS ?? '300', 'WEBHOOK_TOLERANCE_SECONDS');
const maxBodyBytes = 1024 * 1024;

const server = createServer(async (request, response) => {
  if (request.method === 'GET' && request.url === '/health') {
    return respondJson(response, 200, { status: 'ok' });
  }

  if (request.method !== 'POST' || request.url !== '/webhooks/leaderboard-events') {
    return respondJson(response, 404, { error: 'not_found' });
  }

  try {
    const rawBody = await readRequestBody(request, maxBodyBytes);
    const verification = verifySignature({
      header: request.headers['x-rank-ledger-signature'],
      secret,
      rawBody,
      toleranceSeconds,
    });

    if (!verification.valid) {
      return respondJson(response, 401, { error: verification.reason });
    }

    const payload = parseJson(rawBody);
    if (payload === null) {
      return respondJson(response, 400, { error: 'invalid_json' });
    }

    // Demo logs deliberately omit the full payload: production webhooks can
    // contain customer or participant data that does not belong in plain logs.
    console.info(JSON.stringify({
      event: 'webhook_received',
      eventId: payload.id ?? null,
      eventType: payload.type ?? null,
      occurredAt: new Date().toISOString(),
    }));

    return respondJson(response, responseStatus, { accepted: responseStatus >= 200 && responseStatus < 300 });
  } catch (error) {
    if (error.code === 'BODY_TOO_LARGE') {
      return respondJson(response, 413, { error: 'payload_too_large' });
    }

    console.error(JSON.stringify({ event: 'webhook_receiver_error', message: error.message }));
    return respondJson(response, 500, { error: 'internal_error' });
  }
});

server.listen(port, '0.0.0.0', () => {
  console.info(JSON.stringify({ event: 'webhook_receiver_started', port }));
});

function parsePort(value) {
  const portNumber = Number(value);

  if (!Number.isInteger(portNumber) || portNumber < 1 || portNumber > 65535) {
    throw new Error('WEBHOOK_PORT must be a valid TCP port.');
  }

  return portNumber;
}

function parseStatus(value) {
  const status = Number(value);

  if (!Number.isInteger(status) || status < 100 || status > 599) {
    throw new Error('WEBHOOK_RESPONSE_STATUS must be a valid HTTP status.');
  }

  return status;
}

function parsePositiveInteger(value, variableName) {
  const number = Number(value);

  if (!Number.isInteger(number) || number < 1) {
    throw new Error(`${variableName} must be a positive integer.`);
  }

  return number;
}

function readRequestBody(request, maximumBytes) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let receivedBytes = 0;
    let rejected = false;

    request.on('data', (chunk) => {
      if (rejected) {
        return;
      }

      receivedBytes += chunk.length;

      if (receivedBytes > maximumBytes) {
        const error = new Error('Request body exceeded maximum size.');
        error.code = 'BODY_TOO_LARGE';
        rejected = true;

        // Drain the request instead of destroying its socket. That leaves the
        // response writable, so callers get the intentional 413 instead of a
        // misleading network failure.
        request.resume();
        reject(error);
        return;
      }

      chunks.push(chunk);
    });
    request.on('end', () => {
      if (!rejected) {
        resolve(Buffer.concat(chunks));
      }
    });
    request.on('error', reject);
  });
}

function parseJson(rawBody) {
  try {
    return JSON.parse(rawBody.toString('utf8'));
  } catch {
    return null;
  }
}

function respondJson(response, status, body) {
  const payload = JSON.stringify(body);

  response.writeHead(status, {
    'content-type': 'application/json; charset=utf-8',
    'content-length': Buffer.byteLength(payload),
  });
  response.end(payload);
}
