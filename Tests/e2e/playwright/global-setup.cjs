const { request } = require('@playwright/test');
const {
  baseUrl,
  buildZip,
  startStand,
  waitForJoomla,
  collectDiagnostics,
  targetName
} = require('./helpers.cjs');

module.exports = async () => {
  try {
    console.log(`Starting ${targetName()} at ${baseUrl()}`);
    buildZip();
    startStand();

    const context = await request.newContext();

    try {
      await waitForJoomla(context);
    } finally {
      await context.dispose();
    }
  } catch (error) {
    console.error(collectDiagnostics());
    throw error;
  }
};
