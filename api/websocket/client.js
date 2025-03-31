class OrderWebSocketClient {
    constructor() {
        this.connect();
        this.handlers = new Map();
    }

    connect() {
        this.ws = new WebSocket('ws://localhost:8080');
        
        this.ws.onopen = () => {
            console.log('Connected to WebSocket server');
        };

        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            if (this.handlers.has(data.type)) {
                this.handlers.get(data.type)(data);
            }
        };

        this.ws.onclose = () => {
            console.log('Disconnected from WebSocket server');
            setTimeout(() => this.connect(), 5000);
        };
    }

    on(type, handler) {
        this.handlers.set(type, handler);
    }

    send(data) {
        if (this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
        }
    }
}

// Usage example:
const wsClient = new OrderWebSocketClient();
wsClient.on('order_updated', (data) => {
    console.log('Order updated:', data);
    // Update UI accordingly
});
