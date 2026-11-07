import crypto from "node:crypto";

/**
 * Vergleicht HMAC-Werte zeitkonstant. Der aufrufende Webhook-Handler muss den Rohbody
 * verwenden, die Header-Formate der Ziel-API prüfen und Ereignis-IDs idempotent speichern.
 */
export function verifyHmac(rawBody, receivedHmac, secret) {
  if (!rawBody || !receivedHmac || !secret) return false;
  const expected = crypto.createHmac("sha256", secret).update(rawBody).digest("base64");
  const expectedBuffer = Buffer.from(expected);
  const receivedBuffer = Buffer.from(receivedHmac);
  return expectedBuffer.length === receivedBuffer.length && crypto.timingSafeEqual(expectedBuffer, receivedBuffer);
}
