<?php

namespace Src\JobSeeker\Domain;

/** The optional personal details a job seeker may choose to print on a CV: none of them is included unless asked for. */
enum CvInclusion: string
{
    case Photo = 'photo';
    case BirthDate = 'birth_date';
    case Gender = 'gender';
    case Religion = 'religion';
    case MaritalStatus = 'marital_status';
    case Nationality = 'nationality';
}
