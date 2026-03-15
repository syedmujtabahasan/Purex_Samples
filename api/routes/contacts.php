<?php
// ==================== CONTACTS ROUTES ====================

// POST /contacts — public (no auth required) — RATE LIMITED
if ($method === 'POST' && !$id) {
    // Rate limit: 3 submissions per 10 minutes per IP
    $contact_limit = (int) env('RATE_LIMIT_CONTACT', 3);
    $contact_window = (int) env('RATE_LIMIT_CONTACT_WINDOW', 600);
    rate_limit('contact', $contact_limit, $contact_window);

    $data = get_json_body();
    $missing = required_fields($data, ['name', 'phone', 'message']);
    if ($missing) error_response("Field '$missing' is required");

    // Validate input lengths
    $name = sanitize($data['name']);
    $phone = sanitize($data['phone']);
    $message = sanitize($data['message']);

    if (strlen($name) > 200) error_response('Name too long');
    if (strlen($phone) > 30) error_response('Phone too long');
    if (strlen($message) > 5000) error_response('Message too long');
    if (!validate_phone($phone)) error_response('Invalid phone number format');

    $email = sanitize($data['email'] ?? '');
    if ($email && !validate_email($email)) error_response('Invalid email format');

    $stmt = $pdo->prepare('INSERT INTO contacts (name, phone, email, category, order_number, message, photo_count) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $name,
        $phone,
        $email,
        sanitize($data['category'] ?? ''),
        sanitize($data['order_number'] ?? $data['orderNo'] ?? ''),
        $message,
        int_val($data['photo_count'] ?? 0),
    ]);
    success_response(['id' => $pdo->lastInsertId()], 'Contact saved');
}

// GET /contacts — admin only
if ($method === 'GET' && !$id) {
    $user = require_auth();
    $stmt = $pdo->prepare('SELECT * FROM contacts ORDER BY created_at DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
}

// DELETE /contacts/{id}
if ($method === 'DELETE' && $id) {
    $user = require_admin();
    $id = int_val($id);
    $pdo->prepare('DELETE FROM contacts WHERE id = ?')->execute([$id]);
    success_response(null, 'Contact deleted');
}

error_response('Invalid contacts endpoint', 404);
