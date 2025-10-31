-- 1. 데이터베이스 생성
CREATE DATABASE IF NOT EXISTS qna_diary DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE qna_diary;

-- 2. 사용자 테이블
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. 질문 테이블 (예시 12개 질문)
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    category VARCHAR(50) NOT NULL
);

INSERT INTO questions (question, category) VALUES
('오늘 기분은 어떤가요?', '감정'),
('오늘 가장 감사한 일은?', '감사'),
('오늘 배우거나 느낀 점은?', '성장'),
('오늘 자신을 칭찬할 일은?', '자기개발'),
('오늘 가장 즐거웠던 순간은?', '행복'),
('오늘 가장 힘들었던 일은?', '감정'),
('오늘 목표를 달성했나요?', '목표'),
('오늘 누군가에게 도움을 줬나요?', '관계'),
('오늘 새로운 시도를 했나요?', '도전'),
('오늘 마음에 남는 말은?', '감정'),
('오늘 자신에게 하고 싶은 말은?', '자기개발'),
('오늘 하루를 한 문장으로 표현한다면?', '회고');

-- 4. 답변 테이블
CREATE TABLE answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    answer_date DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (question_id) REFERENCES questions(id)
);

-- community 테이블 (utf8mb4)
CREATE TABLE community (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- comments 테이블 (utf8mb4)
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES community(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
