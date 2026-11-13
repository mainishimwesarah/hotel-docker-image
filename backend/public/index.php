<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/src/db.php';
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
function respond($data, int $code=200): never { http_response_code($code); echo json_encode($data); exit; }
try {
  $pdo = db();
  if ($path === '/api/health') respond(['status'=>'ok','message'=>'Hotel API is running']);
  if ($path === '/api/rooms' && $method === 'GET') {
    $q=trim($_GET['q']??'');
    $stmt=$pdo->prepare('SELECT * FROM rooms WHERE room_number LIKE ? OR room_type LIKE ? ORDER BY room_number');
    $like="%$q%"; $stmt->execute([$like,$like]); respond($stmt->fetchAll());
  }
  if ($path === '/api/rooms' && $method === 'POST') {
    foreach(['room_number','room_type','price_per_night','capacity'] as $f) if (!isset($input[$f]) || trim((string)$input[$f])==='') respond(['error'=>"$f is required"],422);
    if (!is_numeric($input['price_per_night']) || $input['price_per_night']<=0 || (int)$input['capacity']<1) respond(['error'=>'Enter a valid price and capacity'],422);
    $stmt=$pdo->prepare('INSERT INTO rooms(room_number,room_type,price_per_night,capacity,description) VALUES(?,?,?,?,?)');
    $stmt->execute([trim($input['room_number']),$input['room_type'],(float)$input['price_per_night'],(int)$input['capacity'],$input['description']??null]);
    respond(['message'=>'Room added successfully','id'=>$pdo->lastInsertId()],201);
  }
  if (preg_match('#^/api/rooms/(\d+)$#',$path,$m)) {
    $id=(int)$m[1];
    if ($method==='PUT') {
      $stmt=$pdo->prepare('UPDATE rooms SET room_number=?,room_type=?,price_per_night=?,capacity=?,description=? WHERE id=?');
      $stmt->execute([$input['room_number']??'', $input['room_type']??'Single', $input['price_per_night']??0, $input['capacity']??1, $input['description']??null,$id]);
      respond(['message'=>'Room updated']);
    }
    if ($method==='DELETE') {
      $c=$pdo->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=? AND status IN ('Reserved','Checked-in')"); $c->execute([$id]);
      if ($c->fetchColumn()) respond(['error'=>'Cancel or complete active bookings before deleting this room'],409);
      $pdo->prepare('DELETE FROM rooms WHERE id=?')->execute([$id]); respond(['message'=>'Room deleted']);
    }
  }
  if ($path==='/api/guests' && $method==='GET') respond($pdo->query('SELECT * FROM guests ORDER BY full_name')->fetchAll());
  if ($path==='/api/guests' && $method==='POST') {
    if (empty(trim($input['full_name']??'')) || !filter_var($input['email']??'',FILTER_VALIDATE_EMAIL) || empty(trim($input['phone']??''))) respond(['error'=>'Enter guest name, valid email and phone'],422);
    $s=$pdo->prepare('INSERT INTO guests(full_name,email,phone) VALUES(?,?,?)'); $s->execute([trim($input['full_name']),trim($input['email']),trim($input['phone'])]);
    respond(['message'=>'Guest registered','id'=>$pdo->lastInsertId()],201);
  }
  if ($path==='/api/bookings' && $method==='GET') {
    $sql='SELECT b.*,r.room_number,r.room_type,g.full_name AS guest_name FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN guests g ON g.id=b.guest_id ORDER BY b.id DESC';
    respond($pdo->query($sql)->fetchAll());
  }
  if ($path==='/api/bookings' && $method==='POST') {
    $room=(int)($input['room_id']??0); $guest=(int)($input['guest_id']??0);
    $in=$input['check_in']??''; $out=$input['check_out']??'';
    if (!$room || !$guest || !$in || !$out || $out<=$in) respond(['error'=>'Select a room and guest, and enter valid dates'],422);
    $pdo->beginTransaction();
    $s=$pdo->prepare("SELECT id FROM rooms WHERE id=? AND status='Available' FOR UPDATE"); $s->execute([$room]);
    if (!$s->fetch()) { $pdo->rollBack(); respond(['error'=>'Room is not available'],409); }
    $over=$pdo->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=? AND status IN ('Reserved','Checked-in') AND check_in < ? AND check_out > ?");
    $over->execute([$room,$out,$in]);
    if ($over->fetchColumn()) { $pdo->rollBack(); respond(['error'=>'Room already has a booking during those dates'],409); }
    $days=(new DateTime($in))->diff(new DateTime($out))->days;
    $price=$pdo->prepare('SELECT price_per_night FROM rooms WHERE id=?'); $price->execute([$room]); $rate=(float)$price->fetchColumn();
    $s=$pdo->prepare("INSERT INTO bookings(room_id,guest_id,check_in,check_out,total_amount) VALUES(?,?,?,?,?)");
    $s->execute([$room,$guest,$in,$out,$days*$rate]); $pdo->commit();
    respond(['message'=>'Reservation created successfully','id'=>$pdo->lastInsertId()],201);
  }
  if (preg_match('#^/api/bookings/(\d+)/(check-in|check-out|cancel)$#',$path,$m) && $method==='POST') {
    $id=(int)$m[1]; $action=$m[2];
    $pdo->beginTransaction();
    $s=$pdo->prepare('SELECT room_id,status FROM bookings WHERE id=? FOR UPDATE'); $s->execute([$id]); $b=$s->fetch();
    if (!$b) { $pdo->rollBack(); respond(['error'=>'Booking not found'],404); }
    if ($action==='check-in' && $b['status']==='Reserved') {
      $pdo->prepare("UPDATE bookings SET status='Checked-in' WHERE id=?")->execute([$id]);
      $pdo->prepare("UPDATE rooms SET status='Occupied' WHERE id=?")->execute([$b['room_id']]);
    } elseif ($action==='check-out' && $b['status']==='Checked-in') {
      $pdo->prepare("UPDATE bookings SET status='Checked-out' WHERE id=?")->execute([$id]);
      $pdo->prepare("UPDATE rooms SET status='Available' WHERE id=?")->execute([$b['room_id']]);
    } elseif ($action==='cancel' && $b['status']==='Reserved') {
      $pdo->prepare("UPDATE bookings SET status='Cancelled' WHERE id=?")->execute([$id]);
    } else { $pdo->rollBack(); respond(['error'=>'This action is not valid for the booking status'],409); }
    $pdo->commit(); respond(['message'=>'Booking updated successfully']);
  }
  if ($path==='/api/stats' && $method==='GET') {
    respond([
      'rooms'=>(int)$pdo->query('SELECT COUNT(*) FROM rooms')->fetchColumn(),
      'available'=>(int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status='Available'")->fetchColumn(),
      'occupied'=>(int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status='Occupied'")->fetchColumn(),
      'guests'=>(int)$pdo->query('SELECT COUNT(*) FROM guests')->fetchColumn(),
      'bookings'=>(int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('Reserved','Checked-in')")->fetchColumn()
    ]);
  }
  respond(['error'=>'Endpoint not found'],404);
} catch (PDOException $e) { respond(['error'=>'Database operation failed','detail'=>$e->getCode()==='23000'?'Room number or guest email may already exist.':'Check database connection and submitted data.'],500); }
catch (Throwable $e) { respond(['error'=>'Server error'],500); }
