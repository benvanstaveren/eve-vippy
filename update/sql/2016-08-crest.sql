alter table characters add column crest_state varchar(500) default null;
alter table characters add column crest_accesstoken varchar(500) default null;
alter table characters add column crest_refreshtoken varchar(500) default null;
alter table characters add column crest_expires varchar(500) default null;
alter table characters add column crest_ownerhash varchar(500) default null;

alter table characters drop column api_keyid;
alter table characters drop column race;
alter table characters drop column bloodline;
alter table characters drop column ancestry;
alter table characters drop column gender;
alter table characters drop column clonename;
alter table characters drop column cloneskillpoints;
alter table characters drop column skillpoints;
alter table characters drop column balance;
alter table characters drop column perceptionbonus;
alter table characters drop column intelligencebonus;
alter table characters drop column memorybonus;
alter table characters drop column willpowerbonus;
alter table characters drop column charismabonus;
alter table characters drop column intelligence;
alter table characters drop column memory;
alter table characters drop column charisma;
alter table characters drop column perception;
alter table characters drop column willpower;
alter table characters drop column dob;