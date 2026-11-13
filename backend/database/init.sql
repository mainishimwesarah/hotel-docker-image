CREATE DATABASE IF NOT EXISTS hotel_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hotel_db;

CREATE TABLE IF NOT EXISTS rooms (
 id INT AUTO_INCREMENT PRIMARY KEY,
 room_number VARCHAR(20) NOT NULL UNIQUE,
 room_type ENUM('Single','Double','Twin','Deluxe','Suite') NOT NULL,
 price_per_night DECIMAL(10,2) NOT NULL,
 capacity INT NOT NULL DEFAULT 1,
 status ENUM('Available','Occupied','Maintenance') NOT NULL DEFAULT 'Available',
 description VARCHAR(255),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS guests (
 id INT AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(150) NOT NULL,
 email VARCHAR(150) NOT NULL UNIQUE,
 phone VARCHAR(30) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS bookings (
 id INT AUTO_INCREMENT PRIMARY KEY,
 room_id INT NOT NULL,
 guest_id INT NOT NULL,
 check_in DATE NOT NULL,
 check_out DATE NOT NULL,
 total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
 status ENUM('Reserved','Checked-in','Checked-out','Cancelled') NOT NULL DEFAULT 'Reserved',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (room_id) REFERENCES rooms(id),
 FOREIGN KEY (guest_id) REFERENCES guests(id),
 CHECK (check_out > check_in)
);
INSERT IGNORE INTO rooms (room_number,room_type,price_per_night,capacity,status,description) VALUES
('101','Single',25000,1,'Available','Comfortable single room'),
('102','Double',40000,2,'Available','Double room with a large bed'),
('201','Deluxe',65000,2,'Available','Deluxe room with city view'),
('202','Suite',100000,3,'Maintenance','Spacious suite');
INSERT IGNORE INTO guests (full_name,email,phone) VALUES
('Demo Guest','guest@example.com','0780000000');
