◆◇◆◇◆◇◆◇◆◇◆◇◆◇◆◇◆◇◆
　　　　　DbDataDiff
◆◇◆◇◆◇◆◇◆◇◆◇◆◇◆◇◆◇◆

■----------------------------■
　　　　　概要
■----------------------------■
同サーバ内にある別テーブルのデータの差分を取って出力するスクリプトです。
オプション指定で汎用データ交換フォーマット json での出力もできます。



■----------------------------■
　　　　　使い方
■----------------------------■
PDOを使っています。
必ず事前にインストールを済ませてください。

-- マニュアルの表示
$ php db_data_diff.php -m

-- test1.ex_tblとtest2.ex_tblの差分を取って表示
$ php db_data_diff.php test1.ex_tbl test2.ex_tbl

-- jsonで出力
$ php db_data_diff.php test1.ex_tbl test2.ex_tbl -j

-- ユーザ名とパスワードを指定
$ php db_data_diff.php test1.ex_tbl test2.ex_tbl -u my_user -p my_pass

-- ホストとポートを指定
$ php db_data_diff.php test1.ex_tbl test2.ex_tbl -u my_user -p my_pass -h my_host -P 3306



■----------------------------■
　　　　　作者
■----------------------------■
setsuki とか yukicon とか Yuki Susugi とか名乗ってますが同じ人です。
https://github.com/setsuki
https://twitter.com/yukiconEx



■----------------------------■
　　　　　ライセンス
■----------------------------■
修正BSDライセンスです。
著作権表示さえしてくれれば好きに扱ってくれて構いません。
ただし無保証です。

