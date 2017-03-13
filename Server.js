/*jshint esversion: 6 */
const Http = require('http');
const net = require('net');
const url = require('url');
const server = Http.createServer(function(req, res) {
    let BufferHelper = require(__dirname + '/bufferhelper.js');
    let bufferHelper = new BufferHelper();
    req.on("data", function(chunk) {
        bufferHelper.concat(chunk);
    });
    req.on('end', function() {
        let body = bufferHelper.toBuffer();
        let r = url.parse(req.url);
        let headers = req.headers;
        delete headers.host;
        delete headers['proxy-connection'];
        headers.connection = 'close';
        headers.x_forwarded_for = '127.0.0.1';
        headers.client_ip = '127.0.0.1';
        let options = {
            hostname: r.host,
            port: r.port || ((r.scheme == 'https') ? 443 : 80),
            path: r.path + (r.query ? '?' + r.query : ''),
            method: req.method,
            headers: headers
        };
        console.log(options);
        let request = Http.request(options, (response) => {
            let bufferHelper = new BufferHelper();
            response.on("data", function(chunk) {
                bufferHelper.concat(chunk);
            });
            response.on('end', function() {
                let html = bufferHelper.toBuffer();
                res.writeHead(response.statusCode, response.headers);
                res.end(html);
            });
        });
        request.on('error', (e) => {
            console.log(`problem with request: ${e.message}`);
        });
        request.write(body);
        request.end();
    });
});
// 代理https /http也可以走这里
server.on('connect', (req, cltSocket, head) => {
    // connect to an origin server
    let srvUrl = url.parse(`http://${req.url}`);
    let srvSocket = net.connect(srvUrl.port, srvUrl.hostname, () => {
        cltSocket.write('HTTP/1.1 200 Connection Established\r\n' +
            'Proxy-agent: Node.js-Proxy\r\n' +
            '\r\n');
        srvSocket.write(head);
        srvSocket.pipe(cltSocket);
        cltSocket.pipe(srvSocket);
    });
});
server.listen(8089);