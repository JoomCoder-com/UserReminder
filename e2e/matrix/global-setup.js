// A fresh state file (and so a fresh run id) for every full run: names and usernames never
// collide with an earlier run on the same site. Set UR_KEEP_STATE=1 to re-run single specs
// against the records of the last run.
const fs = require('fs');
const path = require('path');
module.exports = () => {
  if (process.env.UR_KEEP_STATE) return;
  const port = new URL(process.env.UR_BASE || 'http://127.0.0.1:8811').port;
  fs.rmSync(path.join(__dirname, '..', '..', 'test-results', `matrix-state-${port}.json`), { force: true });
};
