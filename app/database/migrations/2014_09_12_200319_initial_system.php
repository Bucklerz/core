<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InitialSystem extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create("sys_timeline_entry", function($table) {
            $table->bigIncrements("timeline_entry_id")->unsigned();
            $table->integer("timeline_action_id")->unsigned();
            $table->morphs("owner");
            $table->morphs("extra");
            $table->text("extra_data");
            $table->integer("ip");
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create("sys_timeline_action", function($table) {
            $table->increments("timeline_action_id")->unsigned();
            $table->string("section", 35);
            $table->string("area", 35);
            $table->string("action", 35);
            $table->smallInteger("version");
            $table->text("entry");
            $table->boolean("enabled")->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(array("section", "area", "action"));
        });

        Schema::create("sys_setting", function($table) {
            $table->increments("setting_id")->unsigned();
            $table->string("name", 50);
            $table->text("help_text");
            $table->string("group", 40);
            $table->string("area", 25);
            $table->string("section", 25);
            $table->string("key", 25);
            $table->enum("type", array("string", "int", "double", "bool"));
            $table->text("value");
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists("sys_timeline_entry");
        Schema::dropIfExists("sys_timeline_action");
        Schema::dropIfExists("sys_setting");
    }

}