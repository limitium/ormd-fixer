ORM Designer Fixer
===
This bundle for fix ORM Designer generated YML metadata files.

Add bundle in AppKernel:

  if (in_array($this->getEnvironment(), array('dev', 'test'))) {
    ...
    $bundles[] = new Limitium\ORMDFixer\ORMDFixerBundle();
  }

Call `app/console yaml:fix Acme`

- Create Modelname.orm.yml file from ModelName.dcm.yml
- Fix all references in Modelname.orm.yml
- Delete ModelName.dcm.yml file

Example of input dcm and output orm files:


``src\PDS\StoryBundle\Resources\config\doctrine\Comment.dcm.yml``

  Comment:
    type: entity
    table: comment
    fields:
      id:
        id: true
        type: integer
        generator:
          strategy: AUTO
      message:
        type: text
        nullable: false
      created_at:
        type: datetime
        nullable: false
    manyToOne:
      Story:
        targetEntity: Story
        inversedBy: Comments
        joinColumns:
          story_id:
            referencedColumnName: id
      User:
        targetEntity: User
        inversedBy: Comments
        joinColumns:
          user_id:
            referencedColumnName: id 

``src\PDS\StoryBundle\Resources\config\doctrine\Comment.orm.yml``

  PDS\StoryBundle\Entity\Comment:
    type: entity
    table: comment
    fields:
      id:
        id: true
        type: integer
        generator:
          strategy: AUTO
      message:
        type: text
        nullable: false
      created_at:
        type: datetime
        nullable: false
    manyToOne:
      Story:
        targetEntity: PDS\StoryBundle\Entity\Story
        inversedBy: Comments
        joinColumns:
          story_id:
            referencedColumnName: id
      User:
        targetEntity: PDS\UserBundle\Entity\User
        inversedBy: Comments
        joinColumns:
          user_id:
            referencedColumnName: id