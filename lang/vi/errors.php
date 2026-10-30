<?php

return [
    'unreachable' => 'Không thể kết nối tới :host từ panel. Hãy kiểm tra máy chủ panel có được phép kết nối internet không.',
    'invalid_key' => 'Khóa API :company không hợp lệ. Quản trị viên có thể nhập khóa mới trong cài đặt Explain.',
    'no_credit' => 'Tài khoản :company của khóa này đã hết tín dụng. Quản trị viên có thể nạp thêm tại :company.',
    'model_not_allowed' => 'Khóa :company này không được dùng mô hình đã chọn. Quản trị viên có thể chọn mô hình khác trong cài đặt Explain.',
    'model_missing' => 'Mô hình :company đã chọn không khả dụng với khóa này. Quản trị viên có thể chọn mô hình khác trong cài đặt Explain.',
    'busy' => ':company đang bận với khóa này. Hãy thử lại sau một phút.',
    'unavailable' => ':company tạm thời không khả dụng. Hãy thử lại sau một phút.',
    'failed' => ':company không thể trả lời câu hỏi này (lỗi :status). Hãy thử lại sau.',
    'server_unreachable' => 'Bảng điều khiển không kết nối được tới máy chủ :company tại :host. Hãy kiểm tra địa chỉ trong cài đặt Explain và xem :company có đang chạy không.',
    'server_model_missing' => 'Mô hình này không có trên máy chủ :company của bạn. Hãy tải bằng "ollama pull" hoặc nhập mô hình khác trong cài đặt Explain.',
];
