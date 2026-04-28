const { stopStand } = require('./helpers.cjs');

module.exports = async () => {
  if (process.env.E2E_KEEP_STAND === '1') {
    return;
  }

  stopStand();
};
