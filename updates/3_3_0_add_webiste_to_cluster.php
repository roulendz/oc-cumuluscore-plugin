<?php namespace Initbiz\CumulusCore\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddWebisteToCluster extends Migration
{
    public function up()
    {
        if (Schema::hasTable('initbiz_cumuluscore_clusters') && !Schema::hasColumn('initbiz_cumuluscore_clusters', 'website')) {

            Schema::table('initbiz_cumuluscore_clusters', function ($table) {
                $table->string('website')->nullable();
            });
        }
        
    }

    public function down()
    {
        if (Schema::hasTable('initbiz_cumuluscore_clusters') && Schema::hasColumn('initbiz_cumuluscore_clusters', 'website')) {
            Schema::table('initbiz_cumuluscore_clusters', function ($table) {
                $table->dropColumn('website');
            });
        }
        
    }
}
