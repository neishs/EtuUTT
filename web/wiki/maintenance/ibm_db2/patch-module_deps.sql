CREATE  TABLE "MODULE_DEPS" (
"MD_MODULE" VARCHAR(255) FOR BIT DATA  NOT NULL ,
"MD_SKIN" VARCHAR(32) FOR BIT DATA  NOT NULL ,
"MD_DEPS" CLOB(16M) INLINE LENGTH 4096 NOT NULL
)
;