<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class EmpleadosV extends TableGateway
{
    private $idtnom;
    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('a_empleados_vs', $adapter, $databaseSchema,$selectResultPrototype);
    }   
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    

    public function actRegistroValSoc($data=array(),$idUsu)
    {
     
   /*
   Aqui los tatos a ingresar.
    */
      /*Funcion para guardar.*/
       $datos=array
       (
           "idEmp"        => $data["id"],
           "idTipFam"     => $data["famTip"],
           "idTipViv"     => $data["vivienda"],           
           "idEstrap"     => $data["estFam"],           
           "idPro"        => $data["idPro"],           
           "idTipRel"     => $data["tipRel"],                      
           "idNumPer"     => $data["numPer"],
           "conEntrev"    => $data["comenN10"],
           "comentario1"  => $data["comenN4"],    
           "comentario2"  => $data["comenN9"],
           "idConPer"     => $data["conPerl"],
           "idConEc"      => $data["conEcon"],
           "conViv"       => $data["conVivi"],    
           "conAmb"       => $data["conAmb"], 
           "idConSoc"     => $data["conSoc"],
           "fecha"        => $data["fecReg"],
           "ingAdi"       => $data["ingAdi"],                 
           "conOperativo" => $data["conOper"],
           "idUsu"        => $idUsu,            
           
        );
     
          
          $this->insert($datos);
      

     }

    
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $rowset = $this->select(array('id' => $id));
       $row = $rowset->current();
      
      
       return $row;
     }        
     public function delRegistro($id)
     {
       $this->delete(array('id' => $id));               
     }


   
    public function actValoracion($data=array())
    {
       
      
       $id=$data['id']; //id del la tabla a_empleados_vs
       $datos=array
       (
          
           "idTipFam"     => $data["famTip"],
           "idTipViv"     => $data["vivienda"],           
           "idEstrap"     => $data["estFam"],           
           "idPro"        => $data["idPro"],           
           "idTipRel"     => $data["tipRel"],                      
           "idNumPer"     => $data["numPer"],
           "conEntrev"    => $data["comenN10"],
           "comentario1"  => $data["comenN4"],    
           "comentario2"  => $data["comenN9"],
           "idConPer"     => $data["conPerl"],
           "idConEc"      => $data["conEcon"],
           "conViv"       => $data["conVivi"],    
           "conAmb"       => $data["conAmb"], 
           "idConSoc"     => $data["conSoc"],
           "fecha"        => $data["fecReg"],
           "ingAdi"       => $data["ingAdi"],
           "conOperativo" => $data["conOper"]                  
                     
           
        );

       $this->update($datos, array('id' => $id));              
      }
  }
?>
