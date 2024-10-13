// Required when testing against a local Tydom hardware
// to fix "self signed certificate" errors
process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
process.env.DEBUG='tydom-client';

const {createClient} = require('tydom-client');
const chalk = require('chalk');
const prettier = require("prettier");
const log = require('loglevel');

const args = process.argv.slice(2);


log.setLevel('debug');
log.info(`loglevel is set to debug`);
log.debug('Arguments: ');
log.debug(args);

const username = args['user'] || '';
const password = args['password'] || '';

if (args['remote']) {
  hostname = 'mediation.tydom.com';
  log.info(`Remote mode (${hostname})`);
} else { 
    hostname = args['host'] || 'localhost';
    log.info(`Local mode (${hostname})`);
  }

const client = createClient({username, password, hostname});

(async () => {
    log.info(`Connecting to "${hostname}"...`);
    const socket = await client.connect();
    log.info(`Now listening to new messages from hostname=${hostname} (Ctrl-C to exit) ...`);
    client.on('message', (message) => {
      const {type, uri, method, status, body, date} = message;
      const formatedBoby = prettier.format(JSON.stringify(body), { semi: false, parser: 'babel' });
      log.debug(`[${chalk.blue(chalk.bgRed(new Date().toISOString()))}] Received new '${type}' message on Tydom socket, '${method}' on uri=${uri} with status=${status}, body:\n${chalk.yellow(formatedBoby)}`);
    });
})();
