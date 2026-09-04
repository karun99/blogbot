'use strict';

const crypto = require('crypto');
const config = require('./config');

const ALGO = 'aes-256-gcm';

function key() {
  return crypto.createHash('sha256').update(config.encryptionKey).digest();
}

function encrypt(plain) {
  if (!plain) {
    return '';
  }
  const iv = crypto.randomBytes(12);
  const cipher = crypto.createCipheriv(ALGO, key(), iv);
  const enc = Buffer.concat([cipher.update(String(plain), 'utf8'), cipher.final()]);
  const tag = cipher.getAuthTag();
  return Buffer.concat([iv, tag, enc]).toString('base64');
}

function decrypt(payload) {
  if (!payload) {
    return '';
  }
  try {
    const buf = Buffer.from(payload, 'base64');
    const iv = buf.subarray(0, 12);
    const tag = buf.subarray(12, 28);
    const data = buf.subarray(28);
    const decipher = crypto.createDecipheriv(ALGO, key(), iv);
    decipher.setAuthTag(tag);
    return Buffer.concat([decipher.update(data), decipher.final()]).toString('utf8');
  } catch (err) {
    return '';
  }
}

function isEncrypted(payload) {
  if (!payload) {
    return false;
  }
  const buf = Buffer.from(payload, 'base64');
  return buf.length > 28 && decrypt(payload) !== '';
}

module.exports = { encrypt, decrypt, isEncrypted };
