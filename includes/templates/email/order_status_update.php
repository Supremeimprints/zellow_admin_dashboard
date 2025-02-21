<!DOCTYPE html>
<html>
<body>
    <h2>Gift Order Status Update</h2>
    <p>{{MESSAGE}}</p>
    
    <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;">
        <p><strong>Order Status:</strong> {{STATUS}}</p>
        <p><strong>Order ID:</strong> #{{ORDER_ID}}</p>
        <p><strong>Tracking Code:</strong> {{TRACKING_CODE}}</p>
    </div>
    
    <p>Track your gift:</p>
    <p><a href="https://your-domain.com/track?code={{TRACKING_CODE}}" 
          style="background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
       Track Gift
    </a></p>
</body>
</html>
