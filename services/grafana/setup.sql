CREATE DATABASE IF NOT EXISTS wnfit_analytics CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW wnfit_analytics.clients AS
SELECT o.id, o.nome_fantasia AS client_name, o.ativa AS enabled, o.created_at,
 (SELECT COUNT(*) FROM __APP_DB__.alunos a WHERE a.organizacao_id=o.id AND a.perfil_tipo='aluno') AS students,
 (SELECT COUNT(*) FROM __APP_DB__.alunos a WHERE a.organizacao_id=o.id AND a.perfil_tipo='aluno' AND a.status='ativo') AS active_students,
 (SELECT COUNT(*) FROM __APP_DB__.alunos a WHERE a.organizacao_id=o.id AND a.perfil_tipo='participante') AS event_participants,
 (SELECT COUNT(DISTINCT ou.user_id) FROM __APP_DB__.organizacao_usuario ou WHERE ou.organizacao_id=o.id AND ou.status='ativo') AS staff,
 (SELECT COUNT(*) FROM __APP_DB__.treinos t WHERE t.organizacao_id=o.id) AS workouts,
 (SELECT COUNT(*) FROM __APP_DB__.events e WHERE e.organizacao_id=o.id) AS events,
 (SELECT COUNT(*) FROM __APP_DB__.student_accounts sa JOIN __APP_DB__.alunos a ON a.id=sa.aluno_id WHERE a.organizacao_id=o.id AND a.perfil_tipo='aluno') AS portal_accounts,
 (SELECT COUNT(*) FROM __APP_DB__.student_workout_sessions s JOIN __APP_DB__.alunos a ON a.id=s.aluno_id WHERE a.organizacao_id=o.id AND a.perfil_tipo='aluno' AND s.started_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY) AS workout_sessions_30d
FROM __APP_DB__.organizacoes o;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW wnfit_analytics.student_growth AS
SELECT a.organizacao_id AS client_id, a.created_at FROM __APP_DB__.alunos a WHERE a.perfil_tipo='aluno';

CREATE OR REPLACE SQL SECURITY DEFINER VIEW wnfit_analytics.staff AS
SELECT ou.organizacao_id AS client_id, ou.user_id FROM __APP_DB__.organizacao_usuario ou WHERE ou.status='ativo';

CREATE USER IF NOT EXISTS 'wnfit_grafana_reader'@'%' IDENTIFIED BY '__READER_PASSWORD__';
GRANT SELECT ON wnfit_analytics.clients TO 'wnfit_grafana_reader'@'%';
GRANT SELECT ON wnfit_analytics.student_growth TO 'wnfit_grafana_reader'@'%';
GRANT SELECT ON wnfit_analytics.staff TO 'wnfit_grafana_reader'@'%';
