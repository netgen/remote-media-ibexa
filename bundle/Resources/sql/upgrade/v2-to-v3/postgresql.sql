-- Netgen Remote Media 3 tables (netgen/remote-media-bundle and netgen/remote-media-ibexa), generated from the Doctrine mappings.
-- Step 1 of the migration from Netgen Remote Media 2, see MIGRATION.md.
CREATE TABLE ngrm_remote_media_content_link (id SERIAL NOT NULL, remote_resource_location_id INT DEFAULT NULL, field_id INT NOT NULL, version INT NOT NULL, PRIMARY KEY(id));
CREATE UNIQUE INDEX UNIQ_4E6FA05BB29166B0 ON ngrm_remote_media_content_link (remote_resource_location_id);
CREATE UNIQUE INDEX field_id_version_idx ON ngrm_remote_media_content_link (field_id, version);
CREATE TABLE ngrm_remote_resource (id SERIAL NOT NULL, remote_id VARCHAR(255) NOT NULL, type VARCHAR(100) NOT NULL, url VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, md5 VARCHAR(64) NOT NULL, version VARCHAR(100) DEFAULT NULL, folder TEXT DEFAULT NULL, visibility VARCHAR(100) NOT NULL, size INT NOT NULL, alt_text TEXT DEFAULT NULL, caption TEXT DEFAULT NULL, tags TEXT DEFAULT NULL, metadata TEXT DEFAULT NULL, context TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));
CREATE UNIQUE INDEX UNIQ_4B1F1DB42A3E9C94 ON ngrm_remote_resource (remote_id);
COMMENT ON COLUMN ngrm_remote_resource.folder IS '(DC2Type:object)';
COMMENT ON COLUMN ngrm_remote_resource.tags IS '(DC2Type:array)';
COMMENT ON COLUMN ngrm_remote_resource.metadata IS '(DC2Type:array)';
COMMENT ON COLUMN ngrm_remote_resource.context IS '(DC2Type:array)';
COMMENT ON COLUMN ngrm_remote_resource.created_at IS '(DC2Type:datetime_immutable)';
COMMENT ON COLUMN ngrm_remote_resource.updated_at IS '(DC2Type:datetime_immutable)';
CREATE TABLE ngrm_remote_resource_location (id SERIAL NOT NULL, remote_resource_id INT DEFAULT NULL, source VARCHAR(255) DEFAULT NULL, crop_settings TEXT NOT NULL, watermark_text TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id));
CREATE INDEX IDX_76616F4846147E03 ON ngrm_remote_resource_location (remote_resource_id);
COMMENT ON COLUMN ngrm_remote_resource_location.crop_settings IS '(DC2Type:array)';
COMMENT ON COLUMN ngrm_remote_resource_location.created_at IS '(DC2Type:datetime_immutable)';
COMMENT ON COLUMN ngrm_remote_resource_location.updated_at IS '(DC2Type:datetime_immutable)';
ALTER TABLE ngrm_remote_media_content_link ADD CONSTRAINT FK_4E6FA05BB29166B0 FOREIGN KEY (remote_resource_location_id) REFERENCES ngrm_remote_resource_location (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE ngrm_remote_resource_location ADD CONSTRAINT FK_76616F4846147E03 FOREIGN KEY (remote_resource_id) REFERENCES ngrm_remote_resource (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
