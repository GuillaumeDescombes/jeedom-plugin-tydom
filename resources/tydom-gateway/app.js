// Required when testing against a local Tydom hardware
// to fix "self signed certificate" errors
process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
//process.env.NODE_NO_WARNINGS = 1;

//process.env.DEBUG = 'tydom-client';

var express = require('express');
var npid = require('npid');
const path = require('path');
var tiny = require('tiny-json-http')
const log = require('loglevel');
const prefix = require('loglevel-plugin-prefix');

prefix.reg(log);
prefix.apply(log, {
  template: '[%t][%l]',
  levelFormatter: function (level) {
    return level.toUpperCase();
  },
  nameFormatter: function (name) {
    return name || 'root';
  },
  timestampFormatter: function (date) {
    return [date.getFullYear(), ('0' + (date.getMonth() + 1)).slice(-2), ('0' + date.getDate()).slice(-2)].join('-') + " " + date.toTimeString().replace(/.*(\d{2}:\d{2}:\d{2}).*/, '$1');
  },
  format: undefined
});

var args = require('minimist')(process.argv.slice(2));

var loglevel = args['loglevel'] || 'silent';
if (loglevel == "warning") loglevel = "warn"; 
log.setLevel(loglevel);
if (loglevel == 'debug') process.env.DEBUG = 'tydom-client';
log.info(`loglevel is set to ${loglevel}`);

log.debug('Arguments: ');
log.debug(args);

const username = args['user'] || '';
const password = args['password'] || '';

var hostname;
if (args['remote']) {
  hostname = 'mediation.tydom.com';
  log.info(`Remote mode (${hostname})`);
} else {
    hostname = args['host'] || 'localhost';
    log.info(`Local mode (${hostname})`);
  }
const port = args['port'] || 8080;
var callback = args['callback'] ? true : false;
const callBackURL = (args['callback'] || 'http://localhost/callback') + '?apikey=' + args['apikey'];
log.info(`callback URL: ${callBackURL}`);

// creating PID
const pidFile = args['pid'];
if (pidFile) {
  try {
    var pid = npid.create(pidFile);
    pid.removeOnExit();
    log.info(`pid created: "${pidFile}"`);
  } catch (err) {
      log.error(`pid cannot be created ${pidFile}`);
      log.error(err);
      process.exit(1);
  }
}

// checking callback
function isCallbackOK() {
  return new Promise(async function (resolve, reject) {
    try {
      if (callback) {
        const result = await tiny.post({url: callBackURL, data: {action: 'test'}});
        const callbackResult = result['body'] ? JSON.parse(result['body']) : JSON.parse('{success: false}');
        if (callbackResult['success']) {
          log.info('check callback: ok');
          resolve(true);
        } else {
            log.error('check callback: nok');
            log.error(result);
            resolve(false);
          }
      } else {
          log.info('Callback is not enabled');
          resolve(false);
        } 
    } catch (error) {
        log.error('check callback: error');
        log.error(error);
        resolve(false);
      }
  });
}

async function main () {
    callback = await isCallbackOK();

    log.info(`Connecting to "${hostname}"...`);
    const {createClient} = require('tydom-client');
    const client = createClient({username, password, hostname});
    const socket = await client.connect();

    client.on('message', async function (message) {
      const {type, uri, method, status, body, headers, date} = message;
      log.info(`Received new '${type}' message on Tydom socket, '${method}' on uri=${uri} with status=${status}`);
      log.debug('*** trace for body **');
      log.debug(body);
      log.debug('*** end of trace **');
      if (callback) {
          try {
            const result = await tiny.post({url: callBackURL, data: {action: type, method, uri, data: body}});
            const callbackResult = result['body'] ? JSON.parse(result['body']) : JSON.parse('{success: false}');
            if (callbackResult['success']) {
              log.info('Callback: ok');
              log.debug('*** trace for body **');
              log.debug(callbackResult);
              log.debug('*** end of trace **');
            } else {
                log.error('Callback: nok');
                log.error(result);
              }            
          } catch (error) {
              log.error('Callback: error');
              log.error(error);
            }
      }
    });

    try {
      const info = await client.post('/refresh/all');
      log.info('Refreshing the data: ok');
    } catch (error) {
        log.error('Refreshing data: Error');
        log.error(error);
      }

    var app = express();

    app.get('/', async function (req, res) {
      	res.sendFile(path.join(__dirname,'/www/index.html'));
    })
    .get('/favicon.ico', async function (req, res) {
        res.sendFile(path.join(__dirname,'/www/favicon.ico'));
    })
    .get('/info', async function(req, res) {
        const info = await client.get('/info');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(info));
    })
    .get('/devices/data', async function(req, res) {
        const devices = await client.get('/devices/data');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(devices));
    })
   .get('/devices/meta', async function(req, res) {
        const devices = await client.get('/devices/meta');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(devices));
    })
   .get('/devices/cmeta', async function(req, res) {
        const devices = await client.get('/devices/cmeta');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(devices));
    })
    .get('/configs/file', async function(req, res) {
        const configs = await client.get('/configs/file');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(configs));
    })
    .get('/moments/file', async function(req, res) {
        const moments = await client.get('/moments/file');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(moments));
    })
    .get('/scenarios/file', async function(req, res) {
        const scenarios = await client.get('/scenarios/file');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(scenarios));
    })
    .get('/protocols', async function(req, res) {
        const protocols = await client.get('/protocols');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(protocols));
    })
    .get('/device/:decivenum/endpoints', async function(req, res) {
        const info = await client.get('/devices/' + req.params.decivenum + '/endpoints/' + req.params.decivenum + '/data');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(info));
    })
    .get('/devices/:decivenum/endpoints', async function(req, res) {
        const info = await client.get('/devices/' + req.params.decivenum + '/endpoints/' + req.params.decivenum + '/data');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(info));
    })
   .get('/device/:decivenum1/endpoints/:decivenum2/data/:nameParam/:valueParam', async function(req, res) {
        log.info('[TYDOM DEAMON] set ' + req.params.nameParam + ' = ' + req.params.valueParam + ' for DeviceID ' + req.params.decivenum1 + " / EndPointID " + req.params.decivenum2 + ".");
	var json = [{
	    name: req.params.nameParam,
	    value: req.params.valueParam
	}];
	const info = await client.put('/devices/' + req.params.decivenum1 + '/endpoints/' + req.params.decivenum2 + '/data', json);
	res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(info));
    })
   .get('/device/:decivenum1/endpoints/:decivenum2/data', async function(req, res) {
        const info = await client.get('/devices/' + req.params.decivenum1 + '/endpoints/' + req.params.decivenum2 + '/data');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(info));
    })
   .get('/devices/:decivenum1/endpoints/:decivenum2/data', async function(req, res) {
        const info = await client.get('/devices/' + req.params.decivenum1 + '/endpoints/' + req.params.decivenum2 + '/data');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(info));
    })
    .get('/ping', async function(req, res) {
        const info = await client.get('/ping');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(info));
    })
    .get('/refresh', async function(req, res) {
        const info = await client.post('/refresh/all');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(info));
    })
    .get('/refresh/all', async function(req, res) {
        const info = await client.post('/refresh/all');
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(info));
    })
    .get('/stop', function(req, res) {
        log.info('Exiting ...');
	process.exit(0);
    })
    .use(function(req, res, next){
        res.setHeader('Content-Type', 'text/plain');
        res.status(404).send('Page introuvable !');
        log.error('Unknown page : ' + req.originalUrl);
    });

    log.info(`Listening on port  "${port}"...`);
    app.listen(port);
}
main();

