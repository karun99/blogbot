'use strict';

const config = require('./src/config');
const { createApp } = require('./src/app');
const scheduler = require('./src/scheduler');

const app = createApp();

app.listen(config.port, () => {
  console.log(`[BlogWriter] Web app listening on http://0.0.0.0:${config.port}`);
  if (config.nodeEnv !== 'test') {
    scheduler.startScheduler(config.schedulerTickMs);
  }
});
