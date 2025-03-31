<?php
class FirebaseNotification {
    private $serverKey;
    private $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    
    public function __construct() {
        $this->serverKey = getenv('FIREBASE_SERVER_KEY');
    }
    
    public function sendNotification($tokens, $title, $body, $data = []) {
        $fields = [
            'registration_ids' => (array)$tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default'
            ],
            'data' => $data
        ];
        
        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->fcmUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;
    }
}
