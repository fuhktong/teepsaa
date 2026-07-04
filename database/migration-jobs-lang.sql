ALTER TABLE job_postings ADD COLUMN title_km       VARCHAR(150) NULL AFTER title;
ALTER TABLE job_postings ADD COLUMN location_km    VARCHAR(120) NULL AFTER location;
ALTER TABLE job_postings ADD COLUMN description_km TEXT         NULL AFTER description;
