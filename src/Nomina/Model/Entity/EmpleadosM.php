<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class EmpleadosM extends TableGateway
{
    private $idtnom;
    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('a_empleados_me', $adapter, $databaseSchema,$selectResultPrototype);
    }   
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    

       public function actRegistroMujEmb($data=array(),$idUsu)
    {
      $id = $data["id"];

       $idEmp = self::getRegistroId($id);
       $datos=array
       (
           "idEmp"       => $data["id"],
           "numHijo"     => $data["numHij"], 
           "sexo"        => $data["sexo3"],           
           "fecProp"     => $data["fecDoc3"],           
           "historial"   => $data["comenN6"],          
           "fecha"       => $data["fecDoc3"],
           "idUsu"       => $idUsu,
           
        );

      
        if ($idEmp==null) // Nuevo registro
         {
         $this->insert($datos);
         }
       else // Mdificar registro
         {
           $this->update($datos, array('idEmp' => $id));
         }

       
     }

    
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $rowset = $this->select(array('idEmp' => $id));
       $row = $rowset->current();
      
      
       return $row;
     }        
     public function delRegistro($id)
     {
       $this->delete(array('id' => $id));               
     }
}
?>
