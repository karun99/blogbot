'use strict';

const path = require('path');
const express = require('express');
const cors = require('cors');
const config = require('./config');
const routes = require('./routes');

function createApp() {
  const app = express();

  app.disable('x-powered-by');
  app.use(cors());
  app.use(express.json({ limit: '2mb' }));
  app.use(express.urlencoded({ extended: true }));

  app.use(
    '/api',
    (req, res, next) => {
      res.setHeader('X-Frame-Options', 'DENY');
      res.setHeader('X-Content-Type-Options', 'nosniff');
      res.setHeader('X-XSS-Protection', '1; mode=block');
      res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
      next();
    },
    routes
  );

  app.use(express.static(config.publicDir));

  app.get('/', (req, res) => {
    res.sendFile(path.join(config.publicDir, 'index.html'));
  });

  app.use((req, res) => {
    res.status(404).json({ success: false, message: 'Not found' });
  });

  // eslint-disable-next-line no-unused-vars
  app.use((err, req, res, next) => {
    console.error(err);
    res.status(500).json({ success: false, message: 'Internal server error' });
  });

  return app;
}

module.exports = { createApp };
