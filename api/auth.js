import crypto from 'crypto';
import { query } from '../config/db.js';

const JWT_SECRET = process.env.JWT_SECRET || 'dev-testing-portal-secret';
const TOKEN_TTL_SECONDS = Number(process.env.JWT_TTL_SECONDS || 60 * 60 * 8);

function base64UrlEncode(value) {
  return Buffer.from(value)
    .toString('base64')
    .replace(/=/g, '')
    .replace(/\+/g, '-')
    .replace(/\//g, '_');
}

function base64UrlDecode(value) {
  const padded = `${value}${'='.repeat((4 - (value.length % 4)) % 4)}`;
  return Buffer.from(padded.replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString('utf8');
}

function sign(payload) {
  const header = base64UrlEncode(JSON.stringify({ alg: 'HS256', typ: 'JWT' }));
  const body = base64UrlEncode(JSON.stringify(payload));
  const signature = crypto
    .createHmac('sha256', JWT_SECRET)
    .update(`${header}.${body}`)
    .digest('base64')
    .replace(/=/g, '')
    .replace(/\+/g, '-')
    .replace(/\//g, '_');
  return `${header}.${body}.${signature}`;
}

function verify(token) {
  const [header, body, signature] = String(token || '').split('.');
  if (!header || !body || !signature) {
    return null;
  }

  const expected = crypto
    .createHmac('sha256', JWT_SECRET)
    .update(`${header}.${body}`)
    .digest('base64')
    .replace(/=/g, '')
    .replace(/\+/g, '-')
    .replace(/\//g, '_');

  const signatureBuffer = Buffer.from(signature);
  const expectedBuffer = Buffer.from(expected);
  if (signatureBuffer.length !== expectedBuffer.length || !crypto.timingSafeEqual(signatureBuffer, expectedBuffer)) {
    return null;
  }

  const payload = JSON.parse(base64UrlDecode(body));
  if (payload.exp && Date.now() >= payload.exp * 1000) {
    return null;
  }
  return payload;
}

function passwordMatches(inputPassword, storedPassword) {
  const raw = String(inputPassword || '');
  const stored = String(storedPassword || '');
  const md5 = crypto.createHash('md5').update(raw).digest('hex');
  const sha256 = crypto.createHash('sha256').update(raw).digest('hex');
  return stored === raw || stored.toLowerCase() === md5 || stored.toLowerCase() === sha256;
}

async function findUser(email) {
  const normalizedEmail = String(email || '').trim().toLowerCase();
  const adminRows = await query(
    'SELECT id, name, email, password, status, "admin" AS role FROM admin WHERE email = ? LIMIT 1',
    [normalizedEmail],
  );
  if (adminRows.length) {
    return adminRows[0];
  }

  const userRows = await query(
    'SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1',
    [normalizedEmail],
  );
  return userRows[0] || null;
}

export async function login(req, res) {
  try {
    const { email, password } = req.body || {};
    if (!email || !password) {
      return res.status(400).json({ success: false, error: 'Enter your email and password.' });
    }

    const user = await findUser(email);
    if (!user || String(user.status || '').toLowerCase() !== 'active' || !passwordMatches(password, user.password)) {
      return res.status(401).json({ success: false, error: 'Email or password is incorrect.' });
    }

    const now = Math.floor(Date.now() / 1000);
    const safeUser = {
      id: user.id,
      name: user.name,
      email: user.email,
      role: user.role || 'user',
    };
    const token = sign({
      sub: String(user.id),
      email: user.email,
      name: user.name,
      role: user.role || 'user',
      iat: now,
      exp: now + TOKEN_TTL_SECONDS,
    });

    return res.json({ success: true, token, user: safeUser });
  } catch (error) {
    console.error('Login error:', error);
    return res.status(500).json({
      success: false,
      error: 'Unable to sign in right now. Please try again.',
      details: process.env.NODE_ENV === 'development' ? error.message : undefined,
    });
  }
}

export function requireAuth(req, res, next) {
  const header = req.headers.authorization || '';
  const token = header.startsWith('Bearer ') ? header.slice(7) : '';
  const payload = verify(token);
  if (!payload) {
    return res.status(401).json({ success: false, error: 'Please sign in again to continue.' });
  }
  req.user = {
    id: payload.sub,
    email: payload.email,
    name: payload.name,
    role: payload.role,
  };
  return next();
}
